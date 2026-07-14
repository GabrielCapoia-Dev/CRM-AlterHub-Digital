<?php

namespace App\Services\Produtos;

use App\Models\Acesso\User;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\ProdutoReserva;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstoqueService
{
    public function __construct(
        protected MovimentacaoEstoqueService $movimentacaoService,
    ) {}

    public function reservarVenda(
        VendaOperacaoPedido $pedido,
        ?string $idempotencyKey = null,
    ): VendaOperacaoPedido {
        return DB::transaction(function () use ($pedido, $idempotencyKey): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->with('vendasOperacao')
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if ($pedido->vendasOperacao->isEmpty()) {
                throw ValidationException::withMessages([
                    'itens' => 'A venda precisa ter ao menos um item antes da confirmacao.',
                ]);
            }

            $linhasPorProduto = $pedido->vendasOperacao
                ->groupBy('produto_id')
                ->sortKeys();

            /** @var Collection<int, Produto> $produtos */
            $produtos = Produto::query()
                ->whereKey($linhasPorProduto->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($linhasPorProduto as $produtoId => $linhas) {
                $produto = $produtos->get((int) $produtoId);

                if (! $produto || $produto->status !== 'ativo' || ! $produto->ativo) {
                    throw ValidationException::withMessages([
                        'itens' => 'Um dos produtos da venda esta inativo ou indisponivel.',
                    ]);
                }

                $necessario = 0.0;

                foreach ($linhas as $linha) {
                    $reserva = ProdutoReserva::query()
                        ->where('venda_operacao_id', $linha->id)
                        ->lockForUpdate()
                        ->first();

                    if ($reserva?->status === ProdutoReserva::STATUS_ATIVA) {
                        continue;
                    }

                    if ($reserva && (float) $reserva->quantidade_consumida > 0) {
                        throw ValidationException::withMessages([
                            'status' => 'Nao e possivel recriar uma reserva que ja foi consumida.',
                        ]);
                    }

                    $necessario += (float) $linha->quantidade;
                }

                $disponivel = round(
                    (float) $produto->estoque_fisico - (float) $produto->estoque_reservado,
                    4,
                );

                if (round($necessario, 4) > $disponivel) {
                    throw ValidationException::withMessages([
                        'estoque' => sprintf(
                            'Estoque insuficiente para %s. Necessario: %s; disponivel: %s.',
                            $produto->nome,
                            number_format($necessario, 4, ',', '.'),
                            number_format($disponivel, 4, ',', '.'),
                        ),
                    ]);
                }

                foreach ($linhas as $linha) {
                    $reserva = ProdutoReserva::query()
                        ->where('venda_operacao_id', $linha->id)
                        ->lockForUpdate()
                        ->first();

                    if ($reserva?->status === ProdutoReserva::STATUS_ATIVA) {
                        continue;
                    }

                    $key = sprintf(
                        '%s:item:%d',
                        $idempotencyKey ?: "reserva:venda:{$pedido->id}:v{$pedido->versao}",
                        $linha->id,
                    );

                    if ($reserva) {
                        $reserva->forceFill([
                            'quantidade' => $linha->quantidade,
                            'quantidade_consumida' => 0,
                            'status' => ProdutoReserva::STATUS_ATIVA,
                            'idempotency_key' => $key,
                            'liberada_em' => null,
                            'consumida_em' => null,
                        ])->save();
                    } else {
                        ProdutoReserva::query()->create([
                            'venda_operacao_id' => $linha->id,
                            'produto_id' => $produto->id,
                            'quantidade' => $linha->quantidade,
                            'quantidade_consumida' => 0,
                            'status' => ProdutoReserva::STATUS_ATIVA,
                            'idempotency_key' => $key,
                        ]);
                    }
                }

                if ($necessario > 0) {
                    $produto->increment('estoque_reservado', round($necessario, 4));
                }
            }

            return $pedido->fresh(['vendasOperacao.reserva']);
        });
    }

    public function liberarReservasVenda(VendaOperacaoPedido $pedido): void
    {
        DB::transaction(function () use ($pedido): void {
            $linhas = VendaOperacao::query()
                ->where('venda_operacao_pedido_id', $pedido->id)
                ->orderBy('produto_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $produtoIds = $linhas->pluck('produto_id')->unique()->values();
            $produtos = Produto::query()
                ->whereKey($produtoIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($linhas as $linha) {
                $reserva = ProdutoReserva::query()
                    ->where('venda_operacao_id', $linha->id)
                    ->lockForUpdate()
                    ->first();

                if (! $reserva || $reserva->status !== ProdutoReserva::STATUS_ATIVA) {
                    continue;
                }

                $pendente = $reserva->quantidadePendente();

                if ((float) $reserva->quantidade_consumida > 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Ha itens ja despachados. Use o fluxo de devolucao.',
                    ]);
                }

                if ($pendente > 0 && ($produto = $produtos->get($linha->produto_id))) {
                    $produto->decrement('estoque_reservado', $pendente);
                }

                $reserva->forceFill([
                    'status' => ProdutoReserva::STATUS_LIBERADA,
                    'liberada_em' => now(),
                ])->save();
            }
        });
    }

    public function consumirReserva(
        VendaOperacao $linha,
        float $quantidade,
        User $user,
        string $idempotencyKey,
        string $documentoReferencia,
        int $origemId,
    ): ProdutoMovimentacao {
        return DB::transaction(function () use (
            $linha,
            $quantidade,
            $user,
            $idempotencyKey,
            $documentoReferencia,
            $origemId,
        ): ProdutoMovimentacao {
            if ($existente = ProdutoMovimentacao::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existente;
            }

            $linha = VendaOperacao::query()->lockForUpdate()->findOrFail($linha->id);
            $produto = Produto::query()->lockForUpdate()->findOrFail($linha->produto_id);
            $reserva = ProdutoReserva::query()
                ->where('venda_operacao_id', $linha->id)
                ->lockForUpdate()
                ->first();

            $quantidade = round($quantidade, 4);

            if (! $reserva || $reserva->status !== ProdutoReserva::STATUS_ATIVA) {
                throw ValidationException::withMessages([
                    'reserva' => 'A linha nao possui reserva ativa de estoque.',
                ]);
            }

            if ($quantidade <= 0 || $quantidade > $reserva->quantidadePendente()) {
                throw ValidationException::withMessages([
                    'quantidade' => 'A quantidade excede o saldo reservado da linha.',
                ]);
            }

            if ($quantidade > (float) $produto->estoque_fisico) {
                throw ValidationException::withMessages([
                    'quantidade' => 'O saldo fisico ficou inferior ao reservado. Execute a auditoria de estoque.',
                ]);
            }

            $movimentacao = $this->movimentacaoService->createForProduto([
                'produto_id' => $produto->id,
                'tipo' => 'saida',
                'quantidade' => $quantidade,
                'motivo' => 'Despacho de venda',
                'origem_tipo' => 'remessa',
                'origem_id' => $origemId,
                'idempotency_key' => $idempotencyKey,
                'documento_referencia' => $documentoReferencia,
                'origem_destino' => 'Venda operacional',
                'destino' => $linha->cliente_nome,
                'realizado_em' => now(),
                'consome_reserva' => true,
            ], $user);

            $produto->decrement('estoque_reservado', $quantidade);
            $consumida = round((float) $reserva->quantidade_consumida + $quantidade, 4);
            $total = (float) $reserva->quantidade;

            $reserva->forceFill([
                'quantidade_consumida' => $consumida,
                'status' => $consumida >= $total
                    ? ProdutoReserva::STATUS_CONSUMIDA
                    : ProdutoReserva::STATUS_ATIVA,
                'consumida_em' => $consumida >= $total ? now() : null,
            ])->save();

            return $movimentacao;
        });
    }

    /**
     * @return array{produtos:list<array<string, mixed>>,insumos:list<array<string, mixed>>}
     */
    public function auditar(bool $corrigir = false): array
    {
        return DB::transaction(function () use ($corrigir): array {
            $produtoDivergencias = [];

            Produto::query()->orderBy('id')->chunkById(200, function ($produtos) use (&$produtoDivergencias, $corrigir): void {
                foreach ($produtos as $produto) {
                    $fisico = round((float) $produto->produtoMovimentacoes()->sum('impacto_estoque'), 4);
                    $reservado = round((float) ProdutoReserva::query()
                        ->where('produto_id', $produto->id)
                        ->where('status', ProdutoReserva::STATUS_ATIVA)
                        ->selectRaw('COALESCE(SUM(quantidade - quantidade_consumida), 0) AS total')
                        ->value('total'), 4);

                    if ($fisico === round((float) $produto->estoque_fisico, 4)
                        && $reservado === round((float) $produto->estoque_reservado, 4)) {
                        continue;
                    }

                    $produtoDivergencias[] = [
                        'id' => $produto->id,
                        'codigo' => $produto->codigo_interno,
                        'fisico_indexado' => (float) $produto->estoque_fisico,
                        'fisico_razao' => $fisico,
                        'reservado_indexado' => (float) $produto->estoque_reservado,
                        'reservado_razao' => $reservado,
                    ];

                    if ($corrigir) {
                        $produto->forceFill([
                            'estoque_fisico' => $fisico,
                            'estoque_reservado' => $reservado,
                        ])->save();
                    }
                }
            });

            $insumoDivergencias = [];
            \App\Models\Produtos\Insumo::query()->orderBy('id')->chunkById(200, function ($insumos) use (&$insumoDivergencias, $corrigir): void {
                foreach ($insumos as $insumo) {
                    $fisico = round((float) $insumo->insumoMovimentacoes()->sum('impacto_estoque'), 4);
                    $reservado = round((float) $insumo->ordemProducaoInsumos()->sum('quantidade_reservada'), 4);

                    if ($fisico === round((float) $insumo->estoque_fisico, 4)
                        && $reservado === round((float) $insumo->estoque_reservado, 4)) {
                        continue;
                    }

                    $insumoDivergencias[] = [
                        'id' => $insumo->id,
                        'codigo' => $insumo->codigo_interno,
                        'fisico_indexado' => (float) $insumo->estoque_fisico,
                        'fisico_razao' => $fisico,
                        'reservado_indexado' => (float) $insumo->estoque_reservado,
                        'reservado_ordens' => $reservado,
                    ];

                    if ($corrigir) {
                        $insumo->forceFill([
                            'estoque_fisico' => $fisico,
                            'estoque_reservado' => $reservado,
                        ])->save();
                    }
                }
            });

            return ['produtos' => $produtoDivergencias, 'insumos' => $insumoDivergencias];
        });
    }
}
