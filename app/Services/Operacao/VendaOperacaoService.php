<?php

namespace App\Services\Operacao;

use App\Models\Acesso\User;
use App\Models\Produto;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaOperacaoService
{
    public function __construct(
        protected OperacaoAnalyticsService $analyticsService,
        protected MovimentacaoEstoqueService $movimentacaoEstoqueService,
    ) {}

    public function create(array $data, ?User $user = null, ?VendaOperacaoPedido $pedido = null): VendaOperacao
    {
        return DB::transaction(function () use ($data, $user, $pedido): VendaOperacao {
            $produto = Produto::query()
                ->with(['categoriaProduto', 'produtoMovimentacoes'])
                ->lockForUpdate()
                ->findOrFail($data['produto_id']);

            $payload = $this->normalizePayload($data);
            $snapshot = $this->analyticsService->productStockSnapshot($produto);

            $this->validate($produto, $payload, $snapshot['estoque_atual']);

            $custoUnitario = $snapshot['custo_medio'];
            $receitaBruta = round($payload['quantidade'] * $payload['preco_unitario'], 2);
            $icmsValor = round($receitaBruta * ($payload['icms_aliquota'] / 100), 2);
            $outrosImpostosValor = round($receitaBruta * ($payload['outros_impostos_aliquota'] / 100), 2);
            $receitaLiquida = round($receitaBruta - $icmsValor - $outrosImpostosValor, 2);
            $custoTotal = round($custoUnitario * $payload['quantidade'], 2);
            $lucroBruto = round($receitaLiquida - $custoTotal, 2);

            $movimentacao = $this->movimentacaoEstoqueService->createForProduto([
                'produto_id' => $produto->id,
                'tipo' => 'saida',
                'quantidade' => $payload['quantidade'],
                'motivo' => 'Venda operacional',
                'origem_destino' => 'Venda operacional',
                'destino' => $payload['cliente_nome'],
                'realizado_em' => $payload['data_venda'],
                'observacao' => $payload['observacao'],
            ], $user);

            $venda = VendaOperacao::query()->create([
                'user_id' => $user?->id,
                'venda_operacao_pedido_id' => $pedido?->id,
                'produto_id' => $produto->id,
                'produto_movimentacao_id' => $movimentacao->id,
                'produto_codigo_snapshot' => $produto->codigo_interno,
                'produto_nome_snapshot' => $produto->nome,
                'produto_categoria_snapshot' => $produto->categoriaProduto?->nome,
                'unidade_snapshot' => $produto->unidade_medida,
                'data_venda' => $payload['data_venda'],
                'quantidade' => $payload['quantidade'],
                'preco_unitario' => $payload['preco_unitario'],
                'receita_bruta' => $receitaBruta,
                'custo_unitario_snapshot' => $custoUnitario,
                'custo_total_snapshot' => $custoTotal,
                'icms_aliquota' => $payload['icms_aliquota'],
                'icms_valor' => $icmsValor,
                'outros_impostos_aliquota' => $payload['outros_impostos_aliquota'],
                'outros_impostos_valor' => $outrosImpostosValor,
                'receita_liquida' => $receitaLiquida,
                'lucro_bruto' => $lucroBruto,
                'lucro_apos_impostos' => $lucroBruto,
                'cliente_nome' => $payload['cliente_nome'],
                'vendedor_nome' => $payload['vendedor_nome'],
                'observacao' => $payload['observacao'],
            ]);

            $movimentacao->update([
                'documento_referencia' => $pedido?->codigo ?: sprintf('VEN-%05d', $venda->id),
            ]);

            if ($pedido) {
                $this->refreshPedidoTotals($pedido);
            }

            return $venda->fresh(['produto', 'user', 'produtoMovimentacao']);
        });
    }

    public function createPedido(array $data, ?User $user = null): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($data, $user): VendaOperacaoPedido {
            $payload = $this->normalizePayload($data);

            $pedido = VendaOperacaoPedido::query()->create([
                'user_id' => $user?->id,
                'status' => VendaOperacaoPedido::STATUS_ATIVA,
                'data_venda' => $payload['data_venda'],
                'cliente_nome_snapshot' => $payload['cliente_nome'],
                'vendedor_nome_snapshot' => $payload['vendedor_nome'],
                'observacao' => $payload['observacao'],
            ]);
            $pedido->assignCodigo();

            $this->create($data, $user, $pedido);

            return $this->refreshPedidoTotals($pedido)
                ->fresh(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao']);
        });
    }

    public function refreshPedidoTotals(VendaOperacaoPedido $pedido): VendaOperacaoPedido
    {
        $linhas = $pedido->vendasOperacao()->get();

        $pedido->forceFill([
            'itens_count' => $linhas->count(),
            'quantidade_total' => round((float) $linhas->sum('quantidade'), 4),
            'receita_bruta_total' => round((float) $linhas->sum('receita_bruta'), 2),
            'receita_liquida_total' => round((float) $linhas->sum('receita_liquida'), 2),
            'custo_total_snapshot' => round((float) $linhas->sum('custo_total_snapshot'), 2),
            'lucro_bruto_total' => round((float) $linhas->sum('lucro_bruto'), 2),
            'lucro_apos_impostos_total' => round((float) $linhas->sum('lucro_apos_impostos'), 2),
        ])->save();

        return $pedido;
    }

    protected function validate(Produto $produto, array $payload, float $estoqueAtual): void
    {
        $messages = [];

        if ($payload['quantidade'] <= 0) {
            $messages['quantidade'] = 'Informe uma quantidade maior que zero.';
        }

        if ($payload['preco_unitario'] <= 0) {
            $messages['preco_unitario'] = 'Informe um preco unitario maior que zero.';
        }

        if ($produto->status !== 'ativo' || ! $produto->ativo) {
            $messages['produto_id'] = 'O produto precisa estar disponivel para registrar vendas.';
        }

        if ($payload['quantidade'] > $estoqueAtual) {
            $messages['quantidade'] = 'A quantidade informada ultrapassa o saldo atual disponivel do produto.';
        }

        if ($payload['icms_aliquota'] < 0 || $payload['outros_impostos_aliquota'] < 0) {
            $messages['icms_aliquota'] = 'As aliquotas nao podem ser negativas.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function normalizePayload(array $data): array
    {
        return [
            'data_venda' => $data['data_venda'] ?? now()->toDateString(),
            'quantidade' => round((float) ($data['quantidade'] ?? 0), 4),
            'preco_unitario' => round((float) ($data['preco_unitario'] ?? 0), 4),
            'icms_aliquota' => round((float) ($data['icms_aliquota'] ?? 0), 2),
            'outros_impostos_aliquota' => round((float) ($data['outros_impostos_aliquota'] ?? 0), 2),
            'cliente_nome' => $this->normalizeString($data['cliente_nome'] ?? null),
            'vendedor_nome' => $this->normalizeString($data['vendedor_nome'] ?? null),
            'observacao' => $this->normalizeString($data['observacao'] ?? null),
        ];
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
