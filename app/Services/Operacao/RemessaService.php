<?php

namespace App\Services\Operacao;

use App\Enum\RemessaStatus;
use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\ProdutoMovimentacao;
use App\Models\Remessa;
use App\Models\RemessaItem;
use App\Models\Transportadora;
use App\Models\VendaDevolucao;
use App\Models\VendaDevolucaoItem;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Produtos\EstoqueService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemessaService
{
    public function __construct(
        protected EstoqueService $estoqueService,
        protected MovimentacaoEstoqueService $movimentacaoService,
        protected VendaWorkflowService $workflowService,
    ) {}

    /**
     * @param  list<array{venda_operacao_id:int,quantidade:float|int|string}>  $itens
     * @param  array<string, mixed>  $dados
     */
    public function criar(
        VendaOperacaoPedido $pedido,
        array $itens,
        User $actor,
        array $dados = [],
    ): Remessa {
        return DB::transaction(function () use ($pedido, $itens, $actor, $dados): Remessa {
            $idempotencyKey = filled($dados['idempotency_key'] ?? null)
                ? (string) $dados['idempotency_key']
                : null;

            if ($idempotencyKey && ($existente = Remessa::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $existente->load('itens');
            }

            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::ParcialmenteDespachada->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Somente vendas confirmadas com saldo reservado aceitam remessas.',
                ]);
            }

            if ($itens === []) {
                throw ValidationException::withMessages(['itens' => 'Informe ao menos um item para a remessa.']);
            }

            $normalizados = collect($itens)
                ->map(fn (array $item): array => [
                    'venda_operacao_id' => (int) ($item['venda_operacao_id'] ?? 0),
                    'quantidade' => round((float) ($item['quantidade'] ?? 0), 4),
                ])
                ->groupBy('venda_operacao_id')
                ->map(fn ($grupo): array => [
                    'venda_operacao_id' => (int) $grupo->first()['venda_operacao_id'],
                    'quantidade' => round((float) $grupo->sum('quantidade'), 4),
                ])
                ->values();

            $linhas = VendaOperacao::query()
                ->where('venda_operacao_pedido_id', $pedido->id)
                ->whereKey($normalizados->pluck('venda_operacao_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($linhas->count() !== $normalizados->count()) {
                throw ValidationException::withMessages(['itens' => 'Um dos itens nao pertence a esta venda.']);
            }

            foreach ($normalizados as $item) {
                $linha = $linhas->get($item['venda_operacao_id']);
                $alocado = (float) RemessaItem::query()
                    ->where('venda_operacao_id', $linha->id)
                    ->whereHas('remessa', fn ($query) => $query->where('status', '!=', RemessaStatus::Cancelada->value))
                    ->sum('quantidade');
                $saldo = round((float) $linha->quantidade - $alocado, 4);

                if (! $linha->produto_id || $item['quantidade'] <= 0 || $item['quantidade'] > $saldo) {
                    throw ValidationException::withMessages([
                        'itens' => "Quantidade invalida para {$linha->produto_nome_snapshot}. Saldo para remessa: {$saldo}.",
                    ]);
                }
            }

            $modalidade = (string) ($dados['modalidade_entrega'] ?? 'transportadora');

            if (! in_array($modalidade, ['transportadora', 'retirada'], true)) {
                throw ValidationException::withMessages(['modalidade_entrega' => 'Selecione uma modalidade valida.']);
            }

            if ($modalidade === 'transportadora' && blank($dados['transportadora_id'] ?? null)) {
                throw ValidationException::withMessages(['transportadora_id' => 'Selecione a transportadora.']);
            }

            $transportadora = null;

            if ($modalidade === 'transportadora') {
                $transportadora = Transportadora::query()
                    ->whereKey((int) $dados['transportadora_id'])
                    ->where('ativo', true)
                    ->first();

                if (! $transportadora) {
                    throw ValidationException::withMessages([
                        'transportadora_id' => 'Selecione uma transportadora ativa e valida.',
                    ]);
                }
            }

            $freteCusto = $modalidade === 'retirada' ? 0.0 : round((float) ($dados['valor_frete_custo'] ?? 0), 2);
            $freteCobrado = $modalidade === 'retirada' ? 0.0 : round((float) ($dados['valor_frete_cobrado'] ?? 0), 2);

            if (! is_finite($freteCusto) || ! is_finite($freteCobrado) || $freteCusto < 0 || $freteCobrado < 0) {
                throw ValidationException::withMessages([
                    'valor_frete_custo' => 'Os valores de frete devem ser numeros maiores ou iguais a zero.',
                ]);
            }

            $remessa = Remessa::query()->create([
                'venda_operacao_pedido_id' => $pedido->id,
                'transportadora_id' => $transportadora?->id,
                'user_id' => $actor->id,
                'status' => RemessaStatus::Rascunho->value,
                'modalidade_entrega' => $modalidade,
                'idempotency_key' => $idempotencyKey,
                'valor_frete_custo' => $freteCusto,
                'valor_frete_cobrado' => $freteCobrado,
                'codigo_rastreio' => $dados['codigo_rastreio'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ]);

            foreach ($normalizados as $item) {
                $linha = $linhas->get($item['venda_operacao_id']);
                RemessaItem::query()->create([
                    'remessa_id' => $remessa->id,
                    'venda_operacao_id' => $linha->id,
                    'produto_id' => $linha->produto_id,
                    'quantidade' => $item['quantidade'],
                ]);
            }

            $this->sincronizarFrete($pedido);
            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'remessa_criada',
                $pedido->status,
                $pedido->status,
                metadados: ['remessa_id' => $remessa->id],
            );

            return $remessa->fresh(['itens.vendaOperacao', 'pedido']);
        });
    }

    public function iniciarSeparacao(Remessa $remessa, User $actor): Remessa
    {
        return $this->transicionar(
            $remessa,
            $actor,
            [RemessaStatus::Rascunho],
            RemessaStatus::EmSeparacao,
        );
    }

    public function marcarPronta(Remessa $remessa, User $actor): Remessa
    {
        return $this->transicionar(
            $remessa,
            $actor,
            [RemessaStatus::EmSeparacao],
            RemessaStatus::Pronta,
        );
    }

    public function despachar(Remessa $remessa, User $actor, string $idempotencyKey): Remessa
    {
        return DB::transaction(function () use ($remessa, $actor, $idempotencyKey): Remessa {
            $pedidoId = Remessa::query()->whereKey($remessa->id)->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $remessa = Remessa::query()
                ->with('itens.vendaOperacao')
                ->lockForUpdate()
                ->findOrFail($remessa->id);

            if (in_array($remessa->status, [RemessaStatus::Despachada, RemessaStatus::Entregue], true)) {
                return $remessa;
            }

            if ($remessa->status !== RemessaStatus::Pronta) {
                throw ValidationException::withMessages(['status' => 'A remessa precisa estar pronta antes do despacho.']);
            }

            if (! in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::ParcialmenteDespachada->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A venda nao esta mais confirmada para despacho.',
                ]);
            }

            foreach ($remessa->itens->sortBy([
                ['produto_id', 'asc'],
                ['id', 'asc'],
            ]) as $item) {
                $movimentacao = $this->estoqueService->consumirReserva(
                    $item->vendaOperacao,
                    (float) $item->quantidade,
                    $actor,
                    "{$idempotencyKey}:item:{$item->id}",
                    $remessa->codigo ?: "REM-{$remessa->id}",
                    $remessa->id,
                );

                $item->forceFill(['produto_movimentacao_id' => $movimentacao->id])->save();
            }

            $remessa->forceFill([
                'status' => RemessaStatus::Despachada,
                'despachada_em' => now(),
            ])->save();

            $total = round((float) $pedido->vendasOperacao()->sum('quantidade'), 4);
            $despachado = round((float) RemessaItem::query()
                ->whereHas('remessa', fn ($query) => $query
                    ->where('venda_operacao_pedido_id', $pedido->id)
                    ->whereIn('status', [RemessaStatus::Despachada->value, RemessaStatus::Entregue->value]))
                ->sum('quantidade'), 4);

            $novoStatus = $despachado >= $total
                ? VendaStatus::Despachada
                : VendaStatus::ParcialmenteDespachada;

            $this->workflowService->alterarStatusLogistico(
                $pedido,
                $novoStatus,
                $actor,
                ['remessa_id' => $remessa->id, 'quantidade_despachada' => $despachado],
            );

            return $remessa->fresh(['itens.produtoMovimentacao', 'pedido']);
        });
    }

    public function entregar(Remessa $remessa, User $actor): Remessa
    {
        return DB::transaction(function () use ($remessa, $actor): Remessa {
            $pedidoId = Remessa::query()->whereKey($remessa->id)->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $remessa = Remessa::query()->lockForUpdate()->findOrFail($remessa->id);

            if ($remessa->status === RemessaStatus::Entregue) {
                return $remessa;
            }

            if ($remessa->status !== RemessaStatus::Despachada) {
                throw ValidationException::withMessages(['status' => 'Somente remessas despachadas podem ser entregues.']);
            }

            if (in_array($pedido->status, [VendaStatus::DevolvidaParcial->value, VendaStatus::Devolvida->value], true)
                || $remessa->itens()->where('quantidade_devolvida', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'A remessa possui devolucao registrada e nao pode ser marcada como entregue.',
                ]);
            }

            $remessa->forceFill([
                'status' => RemessaStatus::Entregue,
                'entregue_em' => now(),
            ])->save();

            $total = round((float) $pedido->vendasOperacao()->sum('quantidade'), 4);
            $entregue = round((float) RemessaItem::query()
                ->whereHas('remessa', fn ($query) => $query
                    ->where('venda_operacao_pedido_id', $pedido->id)
                    ->where('status', RemessaStatus::Entregue->value))
                ->sum('quantidade'), 4);

            if ($entregue >= $total) {
                $this->workflowService->alterarStatusLogistico(
                    $pedido,
                    VendaStatus::Concluida,
                    $actor,
                    ['remessa_id' => $remessa->id, 'quantidade_entregue' => $entregue],
                );
            } else {
                $this->workflowService->registrarHistorico(
                    $pedido,
                    $actor,
                    'remessa_entregue',
                    $pedido->status,
                    $pedido->status,
                    metadados: ['remessa_id' => $remessa->id],
                );
            }

            return $remessa->fresh(['itens', 'pedido']);
        });
    }

    public function cancelar(Remessa $remessa, User $actor, string $justificativa): Remessa
    {
        return DB::transaction(function () use ($remessa, $actor, $justificativa): Remessa {
            $pedidoId = Remessa::query()->whereKey($remessa->id)->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $remessa = Remessa::query()->lockForUpdate()->findOrFail($remessa->id);

            if ($remessa->status === RemessaStatus::Cancelada) {
                return $remessa;
            }

            if (in_array($remessa->status, [RemessaStatus::Despachada, RemessaStatus::Entregue], true)) {
                throw ValidationException::withMessages(['status' => 'Remessa despachada nao pode ser cancelada. Use devolucao.']);
            }

            if (trim($justificativa) === '') {
                throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa.']);
            }

            $remessa->forceFill([
                'status' => RemessaStatus::Cancelada,
                'cancelada_em' => now(),
                'observacao' => trim(implode("\n", array_filter([$remessa->observacao, "Cancelamento: {$justificativa}"]))),
            ])->save();

            $this->sincronizarFrete($pedido);
            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'remessa_cancelada',
                $pedido->status,
                $pedido->status,
                $justificativa,
                ['remessa_id' => $remessa->id],
            );

            return $remessa->fresh(['itens', 'pedido']);
        });
    }

    /**
     * @param  list<array{remessa_item_id:int,quantidade:float|int|string}>  $itens
     */
    public function devolver(
        VendaOperacaoPedido $pedido,
        array $itens,
        User $actor,
        string $motivo,
        string $idempotencyKey,
    ): VendaDevolucao {
        return DB::transaction(function () use ($pedido, $itens, $actor, $motivo, $idempotencyKey): VendaDevolucao {
            if ($existente = VendaDevolucao::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existente->load('itens.produtoMovimentacao');
            }

            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! in_array($pedido->status, [
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
                VendaStatus::DevolvidaParcial->value,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'A venda ainda nao possui itens elegiveis para devolucao.']);
            }

            if (trim($motivo) === '' || $itens === []) {
                throw ValidationException::withMessages(['motivo' => 'Informe motivo e itens da devolucao.']);
            }

            $normalizados = collect($itens)
                ->map(fn (array $item): array => [
                    'remessa_item_id' => (int) ($item['remessa_item_id'] ?? 0),
                    'quantidade' => round((float) ($item['quantidade'] ?? 0), 4),
                ])
                ->groupBy('remessa_item_id')
                ->map(fn ($grupo): array => [
                    'remessa_item_id' => (int) $grupo->first()['remessa_item_id'],
                    'quantidade' => round((float) $grupo->sum('quantidade'), 4),
                ])
                ->values();

            $remessaItens = RemessaItem::query()
                ->with('remessa')
                ->whereKey($normalizados->pluck('remessa_item_id'))
                ->orderBy('produto_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($normalizados as $item) {
                $remessaItem = $remessaItens->get($item['remessa_item_id']);
                $saldo = $remessaItem
                    ? round((float) $remessaItem->quantidade - (float) $remessaItem->quantidade_devolvida, 4)
                    : 0;

                if (! $remessaItem
                    || $remessaItem->remessa->venda_operacao_pedido_id !== $pedido->id
                    || ! in_array($remessaItem->remessa->status, [RemessaStatus::Despachada, RemessaStatus::Entregue], true)
                    || $item['quantidade'] <= 0
                    || $item['quantidade'] > $saldo) {
                    throw ValidationException::withMessages(['itens' => 'Um item excede o saldo despachado disponivel para devolucao.']);
                }
            }

            $devolucao = VendaDevolucao::query()->create([
                'venda_operacao_pedido_id' => $pedido->id,
                'user_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'motivo' => trim($motivo),
                'recebida_em' => now(),
            ]);

            foreach ($normalizados as $item) {
                $remessaItem = $remessaItens->get($item['remessa_item_id']);
                $movimentacao = $this->movimentacaoService->createForProduto([
                    'produto_id' => $remessaItem->produto_id,
                    'tipo' => 'entrada',
                    'quantidade' => $item['quantidade'],
                    'motivo' => 'Devolucao de venda',
                    'origem_tipo' => 'devolucao',
                    'origem_id' => $devolucao->id,
                    'idempotency_key' => "{$idempotencyKey}:item:{$remessaItem->id}",
                    'documento_referencia' => $devolucao->codigo,
                    'origem_destino' => 'Cliente',
                    'realizado_em' => now(),
                    'observacao' => $motivo,
                ], $actor);

                VendaDevolucaoItem::query()->create([
                    'venda_devolucao_id' => $devolucao->id,
                    'remessa_item_id' => $remessaItem->id,
                    'produto_movimentacao_id' => $movimentacao->id,
                    'quantidade' => $item['quantidade'],
                ]);

                $remessaItem->increment('quantidade_devolvida', $item['quantidade']);
            }

            $despachado = (float) RemessaItem::query()
                ->whereHas('remessa', fn ($query) => $query
                    ->where('venda_operacao_pedido_id', $pedido->id)
                    ->whereIn('status', [RemessaStatus::Despachada->value, RemessaStatus::Entregue->value]))
                ->sum('quantidade');
            $devolvido = (float) RemessaItem::query()
                ->whereHas('remessa', fn ($query) => $query->where('venda_operacao_pedido_id', $pedido->id))
                ->sum('quantidade_devolvida');

            $this->workflowService->atualizarStatusDevolucao(
                $pedido,
                $devolvido >= $despachado ? VendaStatus::Devolvida : VendaStatus::DevolvidaParcial,
                $actor,
                ['devolucao_id' => $devolucao->id, 'quantidade_devolvida' => $devolvido],
            );

            return $devolucao->fresh(['itens.produtoMovimentacao', 'pedido']);
        });
    }

    /** @return list<array<string, mixed>> */
    public function impactoEstoque(Remessa $remessa): array
    {
        return $remessa->loadMissing('itens.produto')->itens
            ->map(fn (RemessaItem $item): array => [
                'produto_id' => $item->produto_id,
                'produto' => $item->produto?->nome,
                'quantidade_a_baixar' => (float) $item->quantidade,
                'estoque_fisico' => (float) ($item->produto?->estoque_fisico ?? 0),
                'estoque_reservado' => (float) ($item->produto?->estoque_reservado ?? 0),
            ])
            ->all();
    }

    /** @param list<RemessaStatus> $origens */
    protected function transicionar(
        Remessa $remessa,
        User $actor,
        array $origens,
        RemessaStatus $destino,
    ): Remessa {
        return DB::transaction(function () use ($remessa, $actor, $origens, $destino): Remessa {
            $pedidoId = Remessa::query()->whereKey($remessa->id)->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedidoId);
            $remessa = Remessa::query()->lockForUpdate()->findOrFail($remessa->id);

            if ($remessa->status === $destino) {
                return $remessa;
            }

            if (! in_array($remessa->status, $origens, true)) {
                throw ValidationException::withMessages(['status' => 'Transicao invalida para a remessa.']);
            }

            if (! in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::ParcialmenteDespachada->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A venda nao esta confirmada para processamento logistico.',
                ]);
            }

            $anterior = $remessa->status;
            $remessa->forceFill(['status' => $destino])->save();
            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'status_remessa',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'remessa_id' => $remessa->id,
                    'status_anterior' => $anterior->value,
                    'status_novo' => $destino->value,
                ],
            );

            return $remessa;
        });
    }

    protected function sincronizarFrete(VendaOperacaoPedido $pedido): void
    {
        $fretes = Remessa::query()
            ->where('venda_operacao_pedido_id', $pedido->id)
            ->where('status', '!=', RemessaStatus::Cancelada->value)
            ->selectRaw('COALESCE(SUM(valor_frete_custo), 0) AS custo, COALESCE(SUM(valor_frete_cobrado), 0) AS cobrado')
            ->first();
        $linhas = $pedido->vendasOperacao()->get();
        $custo = (float) ($fretes?->custo ?? 0);
        $cobrado = (float) ($fretes?->cobrado ?? 0);

        $pedido->forceFill([
            'valor_frete_custo' => $custo,
            'valor_frete_cobrado' => $cobrado,
            'receita_bruta_total' => round((float) $linhas->sum('receita_bruta') + $cobrado, 2),
            'receita_liquida_total' => round((float) $linhas->sum('receita_liquida') + $cobrado, 2),
            'custo_total_snapshot' => round((float) $linhas->sum('custo_total_snapshot') + $custo, 2),
            'lucro_bruto_total' => round((float) $linhas->sum('lucro_bruto') + $cobrado - $custo, 2),
            'lucro_apos_impostos_total' => round((float) $linhas->sum('lucro_apos_impostos') + $cobrado - $custo, 2),
        ])->save();
    }
}
