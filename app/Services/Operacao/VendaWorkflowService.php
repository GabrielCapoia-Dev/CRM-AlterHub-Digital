<?php

namespace App\Services\Operacao;

use App\Enum\RemessaStatus;
use App\Enum\SeparacaoStatus;
use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Oportunidade;
use App\Models\Remessa;
use App\Models\RomaneioPedido;
use App\Models\VendaHistorico;
use App\Models\VendaOperacaoPedido;
use App\Services\Produtos\EstoqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaWorkflowService
{
    public function __construct(
        protected EstoqueService $estoqueService,
    ) {}

    public function confirmar(
        VendaOperacaoPedido $pedido,
        User $actor,
        ?string $idempotencyKey = null,
    ): VendaOperacaoPedido {
        return DB::transaction(function () use ($pedido, $actor, $idempotencyKey): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->with('vendasOperacao')
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if (in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::ParcialmenteDespachada->value,
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
            ], true)) {
                return $pedido;
            }

            if (! in_array($pedido->status, [
                VendaStatus::Rascunho->value,
                VendaStatus::PendenteAprovacao->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'O estado atual da venda nao permite confirmacao.',
                ]);
            }

            $pendencias = $pedido->vendasOperacao
                ->filter(fn ($linha): bool => $linha->desconto_requer_aprovacao && ! $linha->desconto_aprovado_em);

            if ($pendencias->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'A venda possui descontos que ainda dependem de aprovacao.',
                ]);
            }

            $statusAnterior = $pedido->status;
            $this->atualizarTotais($pedido);
            $faltas = $this->estoqueService->faltasVenda($pedido, bloquear: true);
            $chaveIdempotencia = $idempotencyKey ?: "confirmar:venda:{$pedido->id}:v{$pedido->versao}";

            if ($faltas !== []) {
                $motivos = $pedido->motivos_aprovacao ?? [];
                $motivos['estoque'] = $faltas;

                $pedido->forceFill([
                    'status' => VendaStatus::PendenteAprovacao->value,
                    'motivos_aprovacao' => $motivos,
                ])->save();

                $this->registrarPendenciaEstoqueUmaVez(
                    $pedido,
                    $actor,
                    $statusAnterior,
                    $chaveIdempotencia,
                    $faltas,
                );

                return $pedido->fresh(['vendasOperacao.produtoMovimentacao', 'historicos']);
            }

            $this->estoqueService->efetivarVenda(
                $pedido,
                $actor,
                $chaveIdempotencia,
            );

            $pedido->forceFill([
                'status' => VendaStatus::Confirmada->value,
                'separacao_status' => SeparacaoStatus::Aguardando->value,
                'confirmada_em' => $pedido->confirmada_em ?? now(),
                'cancelada_em' => null,
            ])->save();

            $this->registrarHistorico(
                $pedido,
                $actor,
                'confirmada',
                $statusAnterior,
                VendaStatus::Confirmada->value,
                metadados: ['idempotency_key' => $chaveIdempotencia],
            );

            return $pedido->fresh(['vendasOperacao.produtoMovimentacao', 'historicos']);
        });
    }

    public function cancelar(
        VendaOperacaoPedido $pedido,
        User $actor,
        string $justificativa,
    ): VendaOperacaoPedido {
        return DB::transaction(function () use ($pedido, $actor, $justificativa): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertSemRomaneioAtivo($pedido);

            if ($pedido->status === VendaStatus::Cancelada->value) {
                $this->cancelarRemessasPendentes($pedido, $actor, $justificativa);

                return $pedido->fresh(['historicos', 'vendasOperacao.reserva', 'remessas']);
            }

            if ($pedido->hasDispatchedItems() || in_array($pedido->status, [
                VendaStatus::ParcialmenteDespachada->value,
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
                VendaStatus::DevolvidaParcial->value,
                VendaStatus::Devolvida->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A venda possui despacho. Registre uma devolucao em vez de cancelar.',
                ]);
            }

            if (trim($justificativa) === '') {
                throw ValidationException::withMessages([
                    'justificativa' => 'Informe a justificativa do cancelamento.',
                ]);
            }

            $anterior = $pedido->status;
            $remessasCanceladas = $this->cancelarRemessasPendentes($pedido, $actor, $justificativa);
            $oportunidadeLiberadaId = $anterior === VendaStatus::PendenteAprovacao->value
                ? $this->desvincularOportunidadePendente($pedido)
                : null;

            $estornos = 0;

            if ($pedido->status === VendaStatus::Confirmada->value) {
                $estornos = $this->estoqueService->estornarVenda(
                    $pedido,
                    $actor,
                    $justificativa,
                );

                if ($estornos === 0) {
                    $this->estoqueService->liberarReservasVenda($pedido);
                }
            }

            $pedido->forceFill([
                'status' => VendaStatus::Cancelada->value,
                'separacao_status' => null,
                'cancelada_em' => now(),
            ])->save();

            $this->registrarHistorico(
                $pedido,
                $actor,
                'cancelada',
                $anterior,
                VendaStatus::Cancelada->value,
                $justificativa,
                [
                    'remessas_canceladas' => $remessasCanceladas,
                    'movimentacoes_estornadas' => $estornos,
                    'oportunidade_liberada_id' => $oportunidadeLiberadaId,
                ],
            );

            return $pedido->fresh(['historicos', 'vendasOperacao.reserva', 'remessas']);
        });
    }

    public function reabrir(
        VendaOperacaoPedido $pedido,
        User $actor,
        string $justificativa,
    ): VendaOperacaoPedido {
        return DB::transaction(function () use ($pedido, $actor, $justificativa): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertSemRomaneioAtivo($pedido);

            if ($pedido->status === VendaStatus::Rascunho->value) {
                return $pedido;
            }

            if ($pedido->hasDispatchedItems()) {
                throw ValidationException::withMessages([
                    'status' => 'Vendas com despacho nao podem ser reabertas.',
                ]);
            }

            if (! in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::Cancelada->value,
                VendaStatus::Recusada->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'O estado atual da venda nao permite reabertura.',
                ]);
            }

            if (trim($justificativa) === '') {
                throw ValidationException::withMessages([
                    'justificativa' => 'Informe a justificativa da reabertura.',
                ]);
            }

            $this->assertSemDependenciasDocumentais($pedido);

            $anterior = $pedido->status;
            $this->cancelarRemessasPendentes($pedido, $actor, $justificativa);
            $estornos = $this->estoqueService->estornarVenda(
                $pedido,
                $actor,
                $justificativa,
                desvincular: true,
            );

            if ($pedido->status === VendaStatus::Confirmada->value && $estornos === 0) {
                $this->estoqueService->liberarReservasVenda($pedido);
            }

            $pedido->forceFill([
                'status' => VendaStatus::Rascunho->value,
                'versao' => (int) $pedido->versao + 1,
                'reaberta_em' => now(),
                'confirmada_em' => null,
                'cancelada_em' => null,
                'aprovado_por' => null,
                'aprovado_em' => null,
                'motivo_recusa' => null,
                'motivos_aprovacao' => null,
                'separacao_status' => null,
                'separacao_observacao' => null,
                'separado_por' => null,
                'separado_em' => null,
                'retornado_romaneio_em' => null,
                'retorno_romaneio_descricao' => null,
            ])->save();

            $this->registrarHistorico(
                $pedido,
                $actor,
                'reaberta',
                $anterior,
                VendaStatus::Rascunho->value,
                $justificativa,
            );

            return $pedido->fresh(['historicos', 'vendasOperacao.reserva', 'remessas']);
        });
    }

    public function alterarStatusLogistico(
        VendaOperacaoPedido $pedido,
        VendaStatus $novoStatus,
        ?User $actor = null,
        array $metadados = [],
    ): VendaOperacaoPedido {
        return DB::transaction(function () use ($pedido, $novoStatus, $actor, $metadados): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $anterior = $pedido->status;

            if ($anterior === $novoStatus->value) {
                return $pedido;
            }

            $permitidos = [
                VendaStatus::Confirmada->value => [
                    VendaStatus::ParcialmenteDespachada,
                    VendaStatus::Despachada,
                ],
                VendaStatus::ParcialmenteDespachada->value => [
                    VendaStatus::ParcialmenteDespachada,
                    VendaStatus::Despachada,
                ],
                VendaStatus::Despachada->value => [VendaStatus::Concluida],
                VendaStatus::DevolvidaParcial->value => [VendaStatus::Devolvida],
            ];

            if (! in_array($novoStatus, $permitidos[$anterior] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Transicao logistica invalida para a venda.',
                ]);
            }

            $pedido->forceFill([
                'status' => $novoStatus->value,
                'concluida_em' => $novoStatus === VendaStatus::Concluida ? now() : $pedido->concluida_em,
            ])->save();

            $this->registrarHistorico(
                $pedido,
                $actor,
                'status_logistico',
                $anterior,
                $novoStatus->value,
                metadados: $metadados,
            );

            return $pedido;
        });
    }

    public function atualizarStatusDevolucao(
        VendaOperacaoPedido $pedido,
        VendaStatus $novoStatus,
        User $actor,
        array $metadados = [],
    ): VendaOperacaoPedido {
        if (! in_array($novoStatus, [VendaStatus::DevolvidaParcial, VendaStatus::Devolvida], true)) {
            throw ValidationException::withMessages(['status' => 'Estado de devolucao invalido.']);
        }

        return DB::transaction(function () use ($pedido, $novoStatus, $actor, $metadados): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $anterior = $pedido->status;

            if ($anterior === $novoStatus->value) {
                return $pedido;
            }

            if (! in_array($anterior, [
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
                VendaStatus::DevolvidaParcial->value,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'A venda nao aceita devolucao no estado atual.']);
            }

            $pedido->forceFill(['status' => $novoStatus->value])->save();
            $this->registrarHistorico(
                $pedido,
                $actor,
                'devolucao_recebida',
                $anterior,
                $novoStatus->value,
                metadados: $metadados,
            );

            return $pedido;
        });
    }

    public function desvincularOportunidadePendente(VendaOperacaoPedido $pedido): ?int
    {
        return DB::transaction(function () use ($pedido): ?int {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if ($pedido->status !== VendaStatus::PendenteAprovacao->value || ! $pedido->oportunidade_id) {
                return null;
            }

            $oportunidade = Oportunidade::query()
                ->lockForUpdate()
                ->find($pedido->oportunidade_id);

            if (! $oportunidade
                || $oportunidade->convertida_em
                || (int) $oportunidade->venda_operacao_pedido_id !== (int) $pedido->id) {
                return null;
            }

            $oportunidadeId = (int) $oportunidade->id;

            $oportunidade->forceFill([
                'venda_operacao_pedido_id' => null,
            ])->save();

            $pedido->forceFill([
                'oportunidade_id' => null,
            ])->save();

            return $oportunidadeId;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $faltas
     */
    protected function registrarPendenciaEstoqueUmaVez(
        VendaOperacaoPedido $pedido,
        User $actor,
        string $statusAnterior,
        string $idempotencyKey,
        array $faltas,
    ): void {
        $jaRegistrado = VendaHistorico::query()
            ->where('venda_operacao_pedido_id', $pedido->id)
            ->where('evento', 'aguardando_aprovacao_estoque')
            ->where('status_novo', VendaStatus::PendenteAprovacao->value)
            ->get(['metadados'])
            ->contains(
                fn (VendaHistorico $historico): bool => data_get(
                    $historico->metadados,
                    'idempotency_key',
                ) === $idempotencyKey
            );

        if ($jaRegistrado) {
            return;
        }

        $this->registrarHistorico(
            $pedido,
            $actor,
            'aguardando_aprovacao_estoque',
            $statusAnterior,
            VendaStatus::PendenteAprovacao->value,
            metadados: [
                'faltas' => $faltas,
                'idempotency_key' => $idempotencyKey,
            ],
        );
    }

    public function registrarHistorico(
        VendaOperacaoPedido $pedido,
        ?User $actor,
        string $evento,
        ?string $statusAnterior = null,
        ?string $statusNovo = null,
        ?string $justificativa = null,
        array $metadados = [],
    ): VendaHistorico {
        return VendaHistorico::query()->create([
            'venda_operacao_pedido_id' => $pedido->id,
            'user_id' => $actor?->id,
            'evento' => $evento,
            'status_anterior' => $statusAnterior,
            'status_novo' => $statusNovo,
            'justificativa' => $justificativa,
            'metadados' => $metadados === [] ? null : $metadados,
        ]);
    }

    protected function atualizarTotais(VendaOperacaoPedido $pedido): void
    {
        $linhas = $pedido->vendasOperacao()->get();
        $freteCobrado = (float) $pedido->valor_frete_cobrado;
        $freteCusto = (float) $pedido->valor_frete_custo;

        $pedido->forceFill([
            'itens_count' => $linhas->count(),
            'quantidade_total' => round((float) $linhas->sum('quantidade'), 4),
            'receita_bruta_total' => round((float) $linhas->sum('receita_bruta') + $freteCobrado, 2),
            'receita_liquida_total' => round((float) $linhas->sum('receita_liquida') + $freteCobrado, 2),
            'custo_total_snapshot' => round((float) $linhas->sum('custo_total_snapshot') + $freteCusto, 2),
            'lucro_bruto_total' => round((float) $linhas->sum('lucro_bruto') + $freteCobrado - $freteCusto, 2),
            'lucro_apos_impostos_total' => round((float) $linhas->sum('lucro_apos_impostos') + $freteCobrado - $freteCusto, 2),
        ])->save();
    }

    protected function cancelarRemessasPendentes(
        VendaOperacaoPedido $pedido,
        User $actor,
        string $justificativa,
    ): int {
        $remessas = Remessa::query()
            ->where('venda_operacao_pedido_id', $pedido->id)
            ->whereNotIn('status', [
                RemessaStatus::Despachada->value,
                RemessaStatus::Entregue->value,
                RemessaStatus::Cancelada->value,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($remessas as $remessa) {
            $remessa->forceFill([
                'status' => RemessaStatus::Cancelada,
                'cancelada_em' => now(),
                'observacao' => trim(implode("\n", array_filter([
                    $remessa->observacao,
                    sprintf('Cancelada com a venda por %s: %s', $actor->name, trim($justificativa)),
                ]))),
            ])->save();
        }

        if ($remessas->isNotEmpty() || (float) $pedido->valor_frete_custo !== 0.0 || (float) $pedido->valor_frete_cobrado !== 0.0) {
            $pedido->forceFill([
                'valor_frete_custo' => 0,
                'valor_frete_cobrado' => 0,
            ])->save();
            $this->atualizarTotais($pedido);
        }

        return $remessas->count();
    }

    protected function assertSemRomaneioAtivo(VendaOperacaoPedido $pedido): void
    {
        $vinculoAtivo = RomaneioPedido::query()
            ->where('pedido_ativo_id', $pedido->id)
            ->lockForUpdate()
            ->first(['id']);

        if ($vinculoAtivo) {
            throw ValidationException::withMessages([
                'status' => 'Cancele o romaneio ativo deste pedido antes de cancelar ou reabrir a venda.',
            ]);
        }
    }

    protected function assertSemDependenciasDocumentais(VendaOperacaoPedido $pedido): void
    {
        $possuiRomaneio = RomaneioPedido::query()
            ->where('venda_operacao_pedido_id', $pedido->id)
            ->lockForUpdate()
            ->first(['id']) !== null;
        $possuiFotos = $pedido->fotos()
            ->lockForUpdate()
            ->first(['id']) !== null;
        $possuiLotes = $pedido->vendasOperacao()
            ->whereHas('lotes')
            ->lockForUpdate()
            ->first(['id']) !== null;

        if ($possuiRomaneio || $possuiFotos || $possuiLotes) {
            throw ValidationException::withMessages([
                'status' => 'A venda possui lotes, fotos ou historico de romaneio e nao pode ser reaberta sem perder a rastreabilidade.',
            ]);
        }
    }
}
