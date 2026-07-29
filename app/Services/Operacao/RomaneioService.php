<?php

namespace App\Services\Operacao;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\ProdutoMovimentacao;
use App\Models\Romaneio;
use App\Models\RomaneioHistorico;
use App\Models\RomaneioPedido;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Services\Documentos\DocumentoConfiguracaoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RomaneioService
{
    public function __construct(
        protected DocumentoConfiguracaoService $configuracoes,
        protected VendaOperacaoService $vendas,
    ) {}

    public function elegiveisQuery(User $actor): Builder
    {
        return $this->vendas->queryPorPerfil($actor)
            ->where('status', VendaStatus::Confirmada->value)
            ->whereNotNull('data_venda')
            ->where('quantidade_volumes', '>', 0)
            ->whereRaw("TRIM(COALESCE(codigo, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(cliente_nome_snapshot, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(vendedor_nome_snapshot, '')) <> ''")
            ->whereDoesntHave('romaneioPedidoAtivo')
            ->whereHas('vendasOperacao')
            ->whereDoesntHave('vendasOperacao', function (Builder $itens): void {
                $itens->where(function (Builder $invalidos): void {
                    $invalidos
                        ->whereNull('produto_movimentacao_id')
                        ->orWhereNull('peso_unitario_kg_snapshot')
                        ->orWhere('peso_unitario_kg_snapshot', '<=', 0)
                        ->orWhere('quantidade', '<=', 0)
                        ->orWhere('preco_unitario', '<=', 0)
                        ->orWhereRaw("TRIM(COALESCE(produto_nome_snapshot, '')) = ''")
                        ->orWhereRaw("TRIM(COALESCE(unidade_snapshot, '')) = ''")
                        ->orWhereDoesntHave('produtoMovimentacao', function (Builder $movimentacao): void {
                            $movimentacao
                                ->where('origem_tipo', 'venda')
                                ->whereNull('estorno_de_id')
                                ->whereNull('estornada_em')
                                ->whereColumn('produto_movimentacoes.origem_id', 'vendas_operacao.id')
                                ->whereDoesntHave('estorno');
                        });
                });
            });
    }

    /**
     * @param  list<int|string>  $pedidoIds
     */
    public function criar(
        array $pedidoIds,
        User $actor,
        ?string $observacao = null,
    ): Romaneio {
        Gate::forUser($actor)->authorize('create', Romaneio::class);

        $ids = collect($pedidoIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'pedido_ids' => 'Selecione ao menos um pedido elegivel para o romaneio.',
            ]);
        }

        $observacao = $this->nullableString($observacao);

        if ($observacao !== null && mb_strlen($observacao) > 2000) {
            throw ValidationException::withMessages([
                'observacao' => 'A observacao deve possuir no maximo 2.000 caracteres.',
            ]);
        }

        $configuracao = $this->configuracoes->obterValida();
        $empresaSnapshot = [
            ...$this->configuracoes->dadosParaDocumento($configuracao),
            'logo_disk' => (string) ($configuracao->logo_disk ?: 'local'),
            'logo_path' => (string) $configuracao->logo_path,
        ];

        try {
            return DB::transaction(function () use (
                $ids,
                $actor,
                $observacao,
                $empresaSnapshot,
            ): Romaneio {
                $pedidos = $this->vendas->queryPorPerfil($actor)
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $idsEncontrados = $pedidos->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $idsAusentes = array_values(array_diff($ids, $idsEncontrados));

                if ($idsAusentes !== []) {
                    throw ValidationException::withMessages([
                        'pedido_ids' => 'Um ou mais pedidos selecionados nao existem ou nao estao no seu escopo de acesso.',
                    ]);
                }

                foreach ($pedidos as $pedido) {
                    Gate::forUser($actor)->authorize('view', $pedido);
                }

                $itens = VendaOperacao::query()
                    ->whereIn('venda_operacao_pedido_id', $ids)
                    ->orderBy('venda_operacao_pedido_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $movimentacaoIds = $itens
                    ->pluck('produto_movimentacao_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $movimentacoes = ProdutoMovimentacao::query()
                    ->whereIn('id', $movimentacaoIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $estornos = ProdutoMovimentacao::query()
                    ->whereIn('estorno_de_id', $movimentacaoIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('estorno_de_id');
                $vinculosAtivos = RomaneioPedido::query()
                    ->whereIn('pedido_ativo_id', $ids)
                    ->orderBy('pedido_ativo_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('pedido_ativo_id');
                $lotes = VendaOperacaoLote::query()
                    ->whereIn('venda_operacao_id', $itens->pluck('id')->all())
                    ->orderBy('venda_operacao_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->groupBy('venda_operacao_id');
                $itensPorPedido = $itens->groupBy('venda_operacao_pedido_id');

                foreach ($pedidos as $pedido) {
                    $this->assertPedidoElegivel(
                        $pedido,
                        $itensPorPedido->get($pedido->id, collect()),
                        $movimentacoes,
                        $estornos,
                        $vinculosAtivos->has($pedido->id),
                    );
                }

                $romaneio = Romaneio::query()->create([
                    'user_id' => $actor->id,
                    'status' => Romaneio::STATUS_ATIVO,
                    'gerado_em' => now(),
                    'observacao' => $observacao,
                    'empresa_snapshot' => $empresaSnapshot,
                    'exibir_valores_comerciais' => (bool) $empresaSnapshot['exibir_valores_romaneio'],
                ]);
                $romaneio->assignCodigo();

                $totais = [
                    'total_pedidos' => 0,
                    'total_itens' => 0,
                    'quantidade_total' => 0.0,
                    'quantidade_volumes_total' => 0,
                    'peso_total_kg' => 0.0,
                    'valor_total' => 0.0,
                ];
                $clientes = [];

                foreach ($pedidos as $pedido) {
                    $itensPedido = $itensPorPedido->get($pedido->id, collect());
                    $snapshotItens = $itensPedido
                        ->map(fn (VendaOperacao $item): array => $this->snapshotItem(
                            $item,
                            $lotes->get($item->id, collect()),
                        ));
                    $quantidadePedido = round((float) $snapshotItens->sum('quantidade'), 4);
                    $pesoPedido = round((float) $snapshotItens->sum('peso_total_kg'), 4);
                    $valorItens = round((float) $snapshotItens->sum('subtotal'), 2);
                    $valorPedido = round($valorItens + (float) $pedido->valor_frete_cobrado, 2);

                    $romaneioPedido = RomaneioPedido::query()->create([
                        'romaneio_id' => $romaneio->id,
                        'venda_operacao_pedido_id' => $pedido->id,
                        'pedido_ativo_id' => $pedido->id,
                        'cliente_id_snapshot' => $pedido->cliente_id,
                        'pedido_codigo_snapshot' => $pedido->codigo,
                        'pedido_data_snapshot' => $pedido->data_venda?->toDateString(),
                        'cliente_nome_snapshot' => $pedido->cliente_nome_snapshot,
                        'cliente_documento_snapshot' => $pedido->cliente_documento_snapshot,
                        'cliente_telefone_snapshot' => $pedido->cliente_telefone_snapshot,
                        'cliente_email_snapshot' => $pedido->cliente_email_snapshot,
                        'cliente_endereco_snapshot' => $pedido->cliente_endereco_snapshot,
                        'entrega_endereco_snapshot' => $pedido->entrega_endereco_snapshot,
                        'vendedor_nome_snapshot' => $pedido->vendedor_nome_snapshot,
                        'condicao_pagamento_snapshot' => $pedido->condicao_pagamento_snapshot,
                        'condicoes_comerciais_snapshot' => $pedido->condicoes_comerciais,
                        'observacao_snapshot' => $pedido->observacao,
                        'total_itens' => $snapshotItens->count(),
                        'quantidade_total' => $quantidadePedido,
                        'quantidade_volumes' => (int) $pedido->quantidade_volumes,
                        'peso_total_kg' => $pesoPedido,
                        'valor_total' => $valorPedido,
                    ]);

                    foreach ($snapshotItens as $snapshotItem) {
                        $romaneioPedido->itens()->create([
                            ...$snapshotItem,
                            'romaneio_id' => $romaneio->id,
                        ]);
                    }

                    $totais['total_pedidos']++;
                    $totais['total_itens'] += $snapshotItens->count();
                    $totais['quantidade_total'] += $quantidadePedido;
                    $totais['quantidade_volumes_total'] += (int) $pedido->quantidade_volumes;
                    $totais['peso_total_kg'] += $pesoPedido;
                    $totais['valor_total'] += $valorPedido;
                    $chaveCliente = $pedido->cliente_id
                        ? 'id:'.$pedido->cliente_id
                        : 'nome:'.mb_strtolower(trim((string) $pedido->cliente_nome_snapshot));
                    $clientes[$chaveCliente] = true;
                }

                $romaneio->forceFill([
                    ...$totais,
                    'total_clientes' => count($clientes),
                    'quantidade_total' => round($totais['quantidade_total'], 4),
                    'peso_total_kg' => round($totais['peso_total_kg'], 4),
                    'valor_total' => round($totais['valor_total'], 2),
                ])->save();

                RomaneioHistorico::query()->create([
                    'romaneio_id' => $romaneio->id,
                    'user_id' => $actor->id,
                    'evento' => 'criado',
                    'status_anterior' => null,
                    'status_novo' => Romaneio::STATUS_ATIVO,
                    'metadados' => [
                        'pedido_ids' => $ids,
                        'total_pedidos' => $romaneio->total_pedidos,
                        'quantidade_volumes_total' => $romaneio->quantidade_volumes_total,
                        'peso_total_kg' => $romaneio->peso_total_kg,
                    ],
                ]);

                return $romaneio->fresh([
                    'user',
                    'pedidos.itens',
                    'historicos',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isPedidoAtivoUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'pedido_ids' => 'Um dos pedidos selecionados acabou de ser incluido em outro romaneio. Atualize a lista e tente novamente.',
                ]);
            }

            throw $exception;
        }
    }

    public function cancelar(
        Romaneio $romaneio,
        User $actor,
        string $justificativa,
    ): Romaneio {
        Gate::forUser($actor)->authorize('cancel', $romaneio);

        $justificativa = trim($justificativa);

        if (mb_strlen($justificativa) < 10) {
            throw ValidationException::withMessages([
                'justificativa' => 'Informe uma justificativa com ao menos 10 caracteres.',
            ]);
        }

        if (mb_strlen($justificativa) > 2000) {
            throw ValidationException::withMessages([
                'justificativa' => 'A justificativa deve possuir no maximo 2.000 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($romaneio, $actor, $justificativa): Romaneio {
            $romaneio = Romaneio::query()
                ->lockForUpdate()
                ->findOrFail($romaneio->id);
            $vinculos = RomaneioPedido::query()
                ->where('romaneio_id', $romaneio->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! $romaneio->isAtivo()) {
                return $romaneio->fresh(['pedidos.itens', 'historicos', 'canceladoPor']);
            }

            $pedidoIdsLiberados = $vinculos
                ->pluck('pedido_ativo_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            $agora = now();

            RomaneioPedido::query()
                ->whereIn('id', $vinculos->pluck('id')->all())
                ->whereNotNull('pedido_ativo_id')
                ->update([
                    'pedido_ativo_id' => null,
                    'updated_at' => $agora,
                ]);

            $romaneio->forceFill([
                'status' => Romaneio::STATUS_CANCELADO,
                'cancelado_por' => $actor->id,
                'cancelado_em' => $agora,
                'justificativa_cancelamento' => $justificativa,
            ])->save();

            RomaneioHistorico::query()->create([
                'romaneio_id' => $romaneio->id,
                'user_id' => $actor->id,
                'evento' => 'cancelado',
                'status_anterior' => Romaneio::STATUS_ATIVO,
                'status_novo' => Romaneio::STATUS_CANCELADO,
                'justificativa' => $justificativa,
                'metadados' => [
                    'pedido_ids_liberados' => $pedidoIdsLiberados,
                ],
            ]);

            return $romaneio->fresh(['pedidos.itens', 'historicos', 'canceladoPor']);
        }, 3);
    }

    /**
     * @param  Collection<int, VendaOperacao>  $itens
     * @param  Collection<int, ProdutoMovimentacao>  $movimentacoes
     * @param  Collection<int, ProdutoMovimentacao>  $estornos
     */
    protected function assertPedidoElegivel(
        VendaOperacaoPedido $pedido,
        Collection $itens,
        Collection $movimentacoes,
        Collection $estornos,
        bool $possuiVinculoAtivo,
    ): void {
        $pendencias = [];

        if ($pedido->status !== VendaStatus::Confirmada->value) {
            $pendencias[] = 'o status nao e confirmado';
        }

        if (blank($pedido->codigo) || ! $pedido->data_venda) {
            $pendencias[] = 'a identificacao ou data do pedido esta incompleta';
        }

        if (blank($pedido->cliente_nome_snapshot)) {
            $pendencias[] = 'o cliente nao esta identificado';
        }

        if (blank($pedido->vendedor_nome_snapshot)) {
            $pendencias[] = 'o vendedor nao esta identificado';
        }

        if ((int) $pedido->quantidade_volumes <= 0) {
            $pendencias[] = 'a quantidade de volumes nao foi informada';
        }

        if ($possuiVinculoAtivo) {
            $pendencias[] = 'o pedido ja pertence a um romaneio ativo';
        }

        if ($itens->isEmpty()) {
            $pendencias[] = 'o pedido nao possui itens';
        }

        foreach ($itens as $item) {
            $rotulo = $item->produto_nome_snapshot ?: 'item '.$item->id;
            $peso = (float) $item->peso_unitario_kg_snapshot;
            $quantidade = (float) $item->quantidade;
            $preco = (float) $item->preco_unitario;
            $movimentacao = $item->produto_movimentacao_id
                ? $movimentacoes->get((int) $item->produto_movimentacao_id)
                : null;

            if (blank($item->produto_nome_snapshot) || blank($item->unidade_snapshot)) {
                $pendencias[] = "{$rotulo}: os dados do produto estao incompletos";
            }

            if (! is_finite($quantidade) || $quantidade <= 0 || ! is_finite($preco) || $preco <= 0) {
                $pendencias[] = "{$rotulo}: quantidade ou valor unitario invalido";
            }

            if (! is_finite($peso) || $peso <= 0) {
                $pendencias[] = "{$rotulo}: informe um peso unitario maior que zero";
            }

            if (! $movimentacao
                || $movimentacao->origem_tipo !== 'venda'
                || (int) $movimentacao->origem_id !== (int) $item->id
                || $movimentacao->estorno_de_id
                || $movimentacao->estornada_em
                || $estornos->has($movimentacao->id)) {
                $pendencias[] = "{$rotulo}: a movimentacao de estoque da venda esta ausente ou foi estornada";
            }
        }

        if ($pendencias !== []) {
            throw ValidationException::withMessages([
                'pedido_ids' => sprintf(
                    'O pedido %s nao esta elegivel: %s.',
                    $pedido->codigo ?: '#'.$pedido->id,
                    implode('; ', array_values(array_unique($pendencias))),
                ),
            ]);
        }
    }

    /**
     * @param  Collection<int, VendaOperacaoLote>  $lotes
     * @return array<string, mixed>
     */
    protected function snapshotItem(VendaOperacao $item, Collection $lotes): array
    {
        $quantidade = round((float) $item->quantidade, 4);
        $pesoUnitario = round((float) $item->peso_unitario_kg_snapshot, 4);
        $precoUnitario = round((float) $item->preco_unitario, 4);

        return [
            'venda_operacao_id' => $item->id,
            'produto_id' => $item->produto_id,
            'produto_codigo_snapshot' => $item->produto_codigo_snapshot,
            'produto_nome_snapshot' => $item->produto_nome_snapshot,
            'unidade_snapshot' => $item->unidade_snapshot,
            'quantidade' => $quantidade,
            'peso_unitario_kg' => $pesoUnitario,
            'peso_total_kg' => round($quantidade * $pesoUnitario, 4),
            'preco_unitario' => $precoUnitario,
            'subtotal' => round($quantidade * $precoUnitario, 2),
            'lotes_snapshot' => $lotes
                ->map(fn (VendaOperacaoLote $lote): array => [
                    'numero_lote' => $lote->numero_lote,
                    'quantidade' => round((float) $lote->quantidade, 4),
                    'ano_fabricacao' => $lote->ano_fabricacao,
                    'data_fabricacao' => $lote->data_fabricacao?->toDateString(),
                    'data_validade' => $lote->data_validade?->toDateString(),
                    'observacao' => $this->nullableString($lote->observacao),
                ])
                ->values()
                ->all(),
            'observacao_snapshot' => $this->nullableString($item->observacao),
        ];
    }

    protected function isPedidoAtivoUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $mensagem = mb_strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($mensagem, 'pedido_ativo_id');
    }

    protected function nullableString(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
