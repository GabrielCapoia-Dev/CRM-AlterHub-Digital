<?php

namespace Tests\Feature\Operacao;

use App\Models\Produto;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendaOperacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_sale_with_stock_output_and_financial_snapshots(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-01',
            'nome' => 'Produto operacional',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 5.0000,
            'preco_tabela' => 20.00,
            'preco_minimo' => 10.00,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ]);

        $venda = app(VendaOperacaoService::class)->create([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 12,
            'icms_aliquota' => 10,
            'outros_impostos_aliquota' => 5,
            'cliente_nome' => 'Cliente Teste',
            'vendedor_nome' => 'Vendedor Teste',
        ]);

        $this->assertSame(24.0, (float) $venda->receita_bruta);
        $this->assertSame(2.4, (float) $venda->icms_valor);
        $this->assertSame(1.2, (float) $venda->outros_impostos_valor);
        $this->assertSame(20.4, (float) $venda->receita_liquida);
        $this->assertSame(10.0, (float) $venda->custo_total_snapshot);
        $this->assertSame(10.4, (float) $venda->lucro_apos_impostos);
        $this->assertNotNull($venda->produto_movimentacao_id);
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_it_blocks_sale_when_quantity_is_above_available_stock(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-02',
            'nome' => 'Produto sem saldo suficiente',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 3.0000,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 1,
            'valor_unitario' => 3,
            'realizado_em' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(VendaOperacaoService::class)->create([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 15,
        ]);
    }

    public function test_it_creates_a_grouped_sale_header_for_manual_sale(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-03',
            'nome' => 'Produto manual agrupado',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 4.0000,
            'preco_tabela' => 18.00,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 5,
            'valor_unitario' => 4,
            'realizado_em' => now(),
        ]);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 10,
            'cliente_nome' => 'Cliente Manual',
        ]);

        $this->assertSame('ativa', $pedido->status);
        $this->assertSame(1, $pedido->itens_count);
        $this->assertSame(20.0, (float) $pedido->receita_bruta_total);
        $this->assertSame(3.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertDatabaseHas('vendas_operacao', [
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
        ]);
    }
}
