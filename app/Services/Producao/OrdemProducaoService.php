<?php

namespace App\Services\Producao;

use App\Enum\ProdutoClassificacao;
use App\Enum\StatusOrdemProducao;
use App\Models\Acesso\User;
use App\Models\OrdemProducao;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdemProducaoService
{
    public function __construct(
        protected MovimentacaoEstoqueService $movimentacaoEstoque,
    ) {}

    public function criar(array $data, ?User $user = null): OrdemProducao
    {
        return DB::transaction(function () use ($data, $user): OrdemProducao {
            $quantidade = round((float) ($data['quantidade_planejada'] ?? 0), 4);

            if ($quantidade <= 0) {
                throw ValidationException::withMessages([
                    'quantidade_planejada' => 'Informe uma quantidade planejada maior que zero.',
                ]);
            }

            $produto = Produto::query()
                ->lockForUpdate()
                ->findOrFail($data['produto_id'] ?? null);
            $produto->load([
                'produtoInsumos.insumo.tipoUnidadeMedida',
                'produtoInsumos.insumo.insumoFatoresCusto',
            ]);

            if ($produto->classificacao !== ProdutoClassificacao::Fabricado) {
                throw ValidationException::withMessages([
                    'produto_id' => 'Ordens de producao exigem um produto classificado como fabricado.',
                ]);
            }

            if ($produto->produtoInsumos->isEmpty()) {
                throw ValidationException::withMessages([
                    'produto_id' => 'O produto fabricado precisa de uma ficha tecnica com ao menos um insumo.',
                ]);
            }

            $ordem = OrdemProducao::query()->create([
                'codigo' => $data['codigo'] ?? null,
                'produto_id' => $produto->id,
                'user_id' => $data['user_id'] ?? $user?->id,
                'status' => StatusOrdemProducao::Planejada,
                'quantidade_planejada' => $quantidade,
                'quantidade_produzida' => 0,
                'unidade_snapshot' => $produto->unidade_medida,
                'produto_codigo_snapshot' => $produto->codigo_interno,
                'produto_nome_snapshot' => $produto->nome,
                'custo_unitario_snapshot' => 0,
                'custo_total_planejado_snapshot' => 0,
                'prevista_para' => $data['prevista_para'] ?? null,
                'observacao' => $data['observacao'] ?? null,
            ]);

            $custoTotal = 0.0;

            foreach ($produto->produtoInsumos as $index => $produtoInsumo) {
                $insumo = $produtoInsumo->insumo;

                if (! $insumo) {
                    throw ValidationException::withMessages([
                        'produto_id' => 'A ficha tecnica contem um insumo que nao esta mais disponivel.',
                    ]);
                }

                $quantidadeUnitaria = round((float) $produtoInsumo->quantidade, 4);
                $quantidadeNecessaria = round($quantidadeUnitaria * $quantidade, 4);
                $custoUnitario = (float) $produtoInsumo->custo_unitario_snapshot;

                if ($custoUnitario <= 0) {
                    $custoUnitario = $insumo->finalCostAmount();
                }

                $custoLinha = round($quantidadeNecessaria * $custoUnitario, 4);
                $custoTotal += $custoLinha;

                $ordem->insumos()->create([
                    'produto_insumo_id' => $produtoInsumo->id,
                    'insumo_id' => $insumo->id,
                    'ordem' => $index,
                    'insumo_codigo_snapshot' => $insumo->codigo_interno,
                    'insumo_nome_snapshot' => $insumo->nome,
                    'unidade_snapshot' => $produtoInsumo->unidade_consumo
                        ?: $insumo->tipoUnidadeMedida?->sigla
                        ?: $insumo->tipoUnidadeMedida?->nome,
                    'quantidade_unitaria_snapshot' => $quantidadeUnitaria,
                    'quantidade_necessaria_snapshot' => $quantidadeNecessaria,
                    'quantidade_reservada' => 0,
                    'quantidade_consumida' => 0,
                    'custo_unitario_snapshot' => round($custoUnitario, 4),
                    'custo_total_snapshot' => $custoLinha,
                ]);
            }

            $ordem->forceFill([
                'custo_unitario_snapshot' => round($custoTotal / $quantidade, 4),
                'custo_total_planejado_snapshot' => round($custoTotal, 4),
            ])->save();

            return $ordem->fresh(['produto', 'insumos.insumo']);
        });
    }

    public function createOrder(array $data, ?User $user = null): OrdemProducao
    {
        return $this->criar($data, $user);
    }

    public function reservarInsumos(OrdemProducao|int $ordem): OrdemProducao
    {
        return DB::transaction(function () use ($ordem): OrdemProducao {
            $locked = $this->lockOrder($ordem);

            if ($locked->status === StatusOrdemProducao::Reservada) {
                return $locked;
            }

            $this->ensureStatus($locked, StatusOrdemProducao::Planejada, 'reservar os insumos');

            $requirements = $locked->insumos
                ->groupBy('insumo_id')
                ->map(fn (Collection $lines): float => round((float) $lines->sum('quantidade_necessaria_snapshot'), 4));

            $insumos = Insumo::query()
                ->whereIn('id', $requirements->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];

            foreach ($requirements as $insumoId => $necessaria) {
                $insumo = $insumos->get((int) $insumoId);

                if (! $insumo) {
                    $errors['insumos'] = 'Um dos insumos da ordem nao esta mais disponivel.';

                    continue;
                }

                $disponivel = $insumo->estoqueDisponivel();

                if ($necessaria > $disponivel) {
                    $errors["insumos.{$insumo->id}"] = sprintf(
                        'Saldo insuficiente de %s: necessario %.4f, disponivel %.4f.',
                        $insumo->nome,
                        $necessaria,
                        max(0, $disponivel),
                    );
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($requirements as $insumoId => $necessaria) {
                /** @var Insumo $insumo */
                $insumo = $insumos->get((int) $insumoId);
                $insumo->forceFill([
                    'estoque_reservado' => round((float) $insumo->estoque_reservado + $necessaria, 4),
                ])->save();
            }

            foreach ($locked->insumos as $line) {
                $line->forceFill([
                    'quantidade_reservada' => $line->quantidade_necessaria_snapshot,
                ])->save();
            }

            $locked->forceFill([
                'status' => StatusOrdemProducao::Reservada,
                'reservada_em' => now(),
            ])->save();

            return $locked->fresh(['produto', 'insumos.insumo']);
        });
    }

    public function reserveInputs(OrdemProducao|int $ordem): OrdemProducao
    {
        return $this->reservarInsumos($ordem);
    }

    public function liberarInsumos(OrdemProducao|int $ordem): OrdemProducao
    {
        return DB::transaction(function () use ($ordem): OrdemProducao {
            $locked = $this->lockOrder($ordem);

            if ($locked->status === StatusOrdemProducao::Planejada) {
                return $locked;
            }

            $this->ensureStatus($locked, StatusOrdemProducao::Reservada, 'liberar a reserva');

            $this->decrementReservedStock($locked);
            $locked->insumos()->update(['quantidade_reservada' => 0]);
            $locked->forceFill([
                'status' => StatusOrdemProducao::Planejada,
                'reservada_em' => null,
            ])->save();

            return $locked->fresh(['produto', 'insumos.insumo']);
        });
    }

    public function releaseInputs(OrdemProducao|int $ordem): OrdemProducao
    {
        return $this->liberarInsumos($ordem);
    }

    public function consumirInsumos(OrdemProducao|int $ordem, ?User $user = null): OrdemProducao
    {
        return DB::transaction(function () use ($ordem, $user): OrdemProducao {
            $locked = $this->lockOrder($ordem);

            if (in_array($locked->status, [StatusOrdemProducao::EmProducao, StatusOrdemProducao::Concluida], true)) {
                return $locked;
            }

            $this->ensureStatus($locked, StatusOrdemProducao::Reservada, 'consumir os insumos');

            return $this->consumirInsumosBloqueados($locked, $user);
        });
    }

    public function consumeInputs(OrdemProducao|int $ordem, ?User $user = null): OrdemProducao
    {
        return $this->consumirInsumos($ordem, $user);
    }

    public function darEntradaProdutoAcabado(
        OrdemProducao|int $ordem,
        ?float $quantidadeProduzida = null,
        ?User $user = null,
    ): OrdemProducao {
        return DB::transaction(function () use ($ordem, $quantidadeProduzida, $user): OrdemProducao {
            $locked = $this->lockOrder($ordem);

            if ($locked->status === StatusOrdemProducao::Concluida) {
                return $locked;
            }

            if ($locked->status === StatusOrdemProducao::Reservada) {
                $locked = $this->consumirInsumosBloqueados($locked, $user);
                $locked->load(['user', 'produto', 'insumos.insumo']);
            }

            $this->ensureStatus($locked, StatusOrdemProducao::EmProducao, 'dar entrada no produto acabado');

            $quantidade = round($quantidadeProduzida ?? (float) $locked->quantidade_planejada, 4);

            if ($quantidade <= 0) {
                throw ValidationException::withMessages([
                    'quantidade_produzida' => 'Informe uma quantidade produzida maior que zero.',
                ]);
            }

            $movimentacao = $this->movimentacaoEstoque->createForProduto([
                'produto_id' => $locked->produto_id,
                'tipo' => 'entrada',
                'quantidade' => $quantidade,
                'documento_referencia' => $locked->codigo,
                'motivo' => 'Entrada de produto acabado',
                'origem_destino' => 'Producao',
                'valor_unitario' => $locked->custo_unitario_snapshot,
                'valor_total' => round($quantidade * (float) $locked->custo_unitario_snapshot, 4),
                'origem_tipo' => 'ordem_producao',
                'origem_id' => $locked->id,
                'idempotency_key' => sprintf('op:%d:produto:entrada', $locked->id),
                'realizado_em' => now(),
            ], $user ?: $locked->user);

            $locked->forceFill([
                'produto_movimentacao_id' => $movimentacao->id,
                'status' => StatusOrdemProducao::Concluida,
                'quantidade_produzida' => $quantidade,
                'concluida_em' => now(),
            ])->save();

            return $locked->fresh(['produto', 'produtoMovimentacao', 'insumos']);
        });
    }

    public function enterFinishedProduct(
        OrdemProducao|int $ordem,
        ?float $quantidadeProduzida = null,
        ?User $user = null,
    ): OrdemProducao {
        return $this->darEntradaProdutoAcabado($ordem, $quantidadeProduzida, $user);
    }

    public function cancelar(OrdemProducao|int $ordem): OrdemProducao
    {
        return DB::transaction(function () use ($ordem): OrdemProducao {
            $locked = $this->lockOrder($ordem);

            if ($locked->status === StatusOrdemProducao::Cancelada) {
                return $locked;
            }

            if (! in_array($locked->status, [
                StatusOrdemProducao::Planejada,
                StatusOrdemProducao::Reservada,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Somente ordens planejadas ou reservadas podem ser canceladas.',
                ]);
            }

            if ($locked->status === StatusOrdemProducao::Reservada) {
                $this->decrementReservedStock($locked);
                $locked->insumos()->update(['quantidade_reservada' => 0]);
            }
            $locked->forceFill([
                'status' => StatusOrdemProducao::Cancelada,
                'cancelada_em' => now(),
            ])->save();

            return $locked->fresh(['produto', 'insumos']);
        });
    }

    protected function lockOrder(OrdemProducao|int $ordem): OrdemProducao
    {
        $id = $ordem instanceof OrdemProducao ? $ordem->getKey() : $ordem;

        $locked = OrdemProducao::query()->lockForUpdate()->findOrFail($id);
        $locked->load(['user', 'produto', 'insumos.insumo']);

        return $locked;
    }

    protected function ensureStatus(
        OrdemProducao $ordem,
        StatusOrdemProducao $expected,
        string $operation,
    ): void {
        if ($ordem->status === $expected) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'A ordem precisa estar com status "%s" para %s.',
                $expected->label(),
                $operation,
            ),
        ]);
    }

    protected function decrementReservedStock(OrdemProducao $ordem): void
    {
        $reservedByInsumo = $ordem->insumos
            ->groupBy('insumo_id')
            ->map(fn (Collection $lines): float => round((float) $lines->sum('quantidade_reservada'), 4));

        $insumos = Insumo::query()
            ->whereIn('id', $reservedByInsumo->keys()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservedByInsumo as $insumoId => $reserved) {
            /** @var Insumo|null $insumo */
            $insumo = $insumos->get((int) $insumoId);

            if (! $insumo || $reserved <= 0) {
                continue;
            }

            $insumo->forceFill([
                'estoque_reservado' => round(max(0, (float) $insumo->estoque_reservado - $reserved), 4),
            ])->save();
        }
    }

    protected function consumirInsumosBloqueados(OrdemProducao $locked, ?User $user): OrdemProducao
    {
        $assignedUser = $user ?: $locked->user;
        $insumoIds = $locked->insumos->pluck('insumo_id')->unique()->sort()->values()->all();
        Insumo::query()->whereIn('id', $insumoIds)->orderBy('id')->lockForUpdate()->get();
        $this->decrementReservedStock($locked);

        foreach ($locked->insumos as $line) {
            $quantidade = round((float) $line->quantidade_necessaria_snapshot, 4);
            $movimentacao = $this->movimentacaoEstoque->createForInsumo([
                'insumo_id' => $line->insumo_id,
                'tipo' => 'consumo_interno',
                'quantidade' => $quantidade,
                'motivo' => 'Consumo da ordem de producao '.$locked->codigo,
                'documento_referencia' => $locked->codigo,
                'destino' => $locked->produto_nome_snapshot,
                'valor_unitario' => $line->custo_unitario_snapshot,
                'valor_total' => $line->custo_total_snapshot,
                'origem_tipo' => 'ordem_producao',
                'origem_id' => $locked->id,
                'idempotency_key' => sprintf('op:%d:insumo:%d:consumo', $locked->id, $line->id),
                'consome_reserva' => true,
                'realizado_em' => now(),
            ], $assignedUser);

            $line->forceFill([
                'quantidade_reservada' => 0,
                'quantidade_consumida' => $quantidade,
                'insumo_movimentacao_id' => $movimentacao->id,
            ])->save();
        }

        $locked->forceFill([
            'status' => StatusOrdemProducao::EmProducao,
            'iniciada_em' => now(),
        ])->save();

        return $locked->fresh(['user', 'produto', 'insumos.insumoMovimentacao']);
    }
}
