<?php

namespace Tests\Feature\Operacao;

use App\Models\Categorias\CategoriaProduto;
use App\Models\DespesaOperacional;
use App\Models\Produto;
use App\Services\Operacao\OperacaoAnalyticsService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperacaoAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_dre_profit_and_dashboard_snapshots_from_sales_and_expenses(): void
    {
        $categoria = CategoriaProduto::query()->create([
            'nome' => 'Analiticos',
        ]);

        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-BI-01',
            'categoria_produto_id' => $categoria->id,
            'nome' => 'Kit analitico',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'estoque_minimo' => 8,
            'custo_base_formacao' => 10.0000,
            'preco_tabela' => 30.00,
            'preco_minimo' => 20.00,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'valor_unitario' => 10,
            'realizado_em' => now()->subDay(),
        ]);

        app(VendaOperacaoService::class)->create([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 3,
            'preco_unitario' => 25,
            'icms_aliquota' => 10,
            'outros_impostos_aliquota' => 2,
            'cliente_nome' => 'Hospital Central',
            'vendedor_nome' => 'Marina',
        ]);

        DespesaOperacional::query()->create([
            'descricao' => 'Frete geral',
            'categoria' => 'logistica',
            'tipo' => 'variavel',
            'valor' => 50,
            'data_competencia' => now()->toDateString(),
        ]);

        DespesaOperacional::query()->create([
            'produto_id' => $produto->id,
            'descricao' => 'Material promocional',
            'categoria' => 'comercial',
            'tipo' => 'variavel',
            'valor' => 12,
            'data_competencia' => now()->toDateString(),
        ]);

        $service = app(OperacaoAnalyticsService::class);
        $filters = [
            'period_mode' => 'mes',
            'month' => now()->month,
            'year' => now()->year,
        ];

        $dre = $service->getDreSnapshot($filters);
        $profitRows = $service->getProfitByProductRows($filters);
        $dashboard = $service->getDashboardSnapshot($filters);

        $this->assertTrue($dre['ok']);
        $this->assertSame(75.0, $dre['metrics']['receita_bruta']);
        $this->assertSame(7.5, $dre['metrics']['icms_total']);
        $this->assertSame(1.5, $dre['metrics']['outros_impostos_total']);
        $this->assertSame(66.0, $dre['metrics']['receita_liquida']);
        $this->assertSame(30.0, $dre['metrics']['cmv']);
        $this->assertSame(62.0, $dre['metrics']['despesas_operacionais']);
        $this->assertSame(-26.0, $dre['metrics']['lucro_liquido']);

        $this->assertCount(1, $profitRows);
        $this->assertSame(12.0, $profitRows[0]['despesas_alocadas']);
        $this->assertSame(24.0, $profitRows[0]['lucro_liquido']);

        $this->assertTrue($dashboard['ok']);
        $this->assertNotEmpty($dashboard['low_stock_products']);
        $this->assertSame($produto->id, $dashboard['low_stock_products'][0]['produto_id']);
        $this->assertNotEmpty($dashboard['sales_by_day']);
        $this->assertNotEmpty($dashboard['seller_performance']);
    }
}
