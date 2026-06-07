<?php

namespace Tests\Feature\Produtos;

use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\ProdutoPricingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProdutoPricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_snapshots_cost_base_and_suggested_price(): void
    {
        $unidade = TipoUnidadeMedida::query()->create([
            'nome' => 'Unidade',
            'sigla' => 'un',
        ]);

        $insumoA = Insumo::query()->create([
            'codigo_interno' => 'INS-A',
            'nome' => 'Insumo A',
            'origem' => 'nacional',
            'tipo_unidade_medida_id' => $unidade->id,
            'custo_referencia' => 100.0000,
        ]);

        $insumoB = Insumo::query()->create([
            'codigo_interno' => 'INS-B',
            'nome' => 'Insumo B',
            'origem' => 'nacional',
            'tipo_unidade_medida_id' => $unidade->id,
            'custo_referencia' => 50.0000,
        ]);

        $service = app(ProdutoPricingCalculator::class);

        $prepared = $service->prepareForPersistence([
            'status' => 'ativo',
            'preco_tabela' => 500.00,
            'preco_minimo' => 450.00,
            'produtoInsumos' => [
                ['insumo_id' => $insumoA->id, 'quantidade' => 2],
                ['insumo_id' => $insumoB->id, 'quantidade' => 1],
            ],
            'produtoComponentesCusto' => [
                $this->makeComponent('Comissao', 'comerciais', 'percentual_sobre_venda', 5),
                $this->makeComponent('ICMS', 'impostos', 'percentual_sobre_venda', 30),
                $this->makeComponent('Frete', 'custos_fixos', 'valor_fixo_brl', 20),
            ],
        ]);

        $this->assertSame('un', $prepared['produtoInsumos'][0]['unidade_consumo']);
        $this->assertSame(100.0, $prepared['produtoInsumos'][0]['custo_unitario_snapshot']);
        $this->assertSame(200.0, $prepared['produtoInsumos'][0]['custo_total_snapshot']);
        $this->assertSame('un', $prepared['produtoInsumos'][1]['unidade_consumo']);
        $this->assertSame(50.0, $prepared['produtoInsumos'][1]['custo_unitario_snapshot']);
        $this->assertSame(50.0, $prepared['produtoInsumos'][1]['custo_total_snapshot']);
        $this->assertSame(270.0, $prepared['custo_base_formacao']);
        $this->assertEqualsWithDelta(415.38, $prepared['preco_sugerido'], 0.01);
        $this->assertTrue($prepared['ativo']);
    }

    public function test_it_blocks_products_when_percentual_total_reaches_one_hundred_percent(): void
    {
        $service = app(ProdutoPricingCalculator::class);

        $this->expectException(ValidationException::class);

        $service->prepareForPersistence([
            'status' => 'ativo',
            'preco_tabela' => 100.00,
            'preco_minimo' => 90.00,
            'produtoInsumos' => [],
            'produtoComponentesCusto' => [
                $this->makeComponent('Comissao', 'comerciais', 'percentual_sobre_venda', 40),
                $this->makeComponent('ICMS', 'impostos', 'percentual_sobre_venda', 60),
            ],
        ]);
    }

    public function test_it_blocks_when_preco_minimo_is_greater_than_preco_base(): void
    {
        $service = app(ProdutoPricingCalculator::class);

        $this->expectException(ValidationException::class);

        $service->prepareForPersistence([
            'status' => 'ativo',
            'preco_tabela' => 100.00,
            'preco_minimo' => 120.00,
            'produtoInsumos' => [],
            'produtoComponentesCusto' => [
                $this->makeComponent('Frete', 'custos_fixos', 'valor_fixo_brl', 10),
            ],
        ]);
    }

    public function test_default_components_do_not_include_margin(): void
    {
        $componentes = collect(app(ProdutoPricingCalculator::class)->defaultComponentes())
            ->pluck('nome');

        $this->assertFalse($componentes->contains('Margem'));
    }

    public function test_it_calculates_single_product_cost_with_arrival_profit_and_output_taxes(): void
    {
        $service = app(ProdutoPricingCalculator::class);

        $prepared = $service->prepareForPersistence([
            'status' => 'ativo',
            'preco_tabela' => 400.00,
            'preco_minimo' => 350.00,
            'produtoInsumos' => [],
            'produtoComponentesCusto' => [
                $this->makeComponent('Custo do produto', 'custo_produto', 'valor_fixo_brl', 120),
                $this->makeComponent('Frete de entrada', 'custo_entrada', 'valor_fixo_brl', 10),
                $this->makeComponent('Imposto de importacao', 'imposto_entrada', 'valor_fixo_brl', 30),
                $this->makeComponent('Embalagem', 'custo_producao', 'valor_fixo_brl', 10),
                $this->makeComponent('Lucro desejado', 'lucro', 'percentual_sobre_venda', 20),
                $this->makeComponent('ICMS saida', 'imposto_saida', 'percentual_sobre_venda', 12),
                $this->makeComponent('Comissao', 'custo_saida', 'percentual_sobre_venda', 3),
            ],
        ]);

        $summary = $service->summarizeState($prepared, refreshSnapshots: false);

        $this->assertSame(170.0, $prepared['custo_base_formacao']);
        $this->assertEqualsWithDelta(261.54, $prepared['preco_sugerido'], 0.01);
        $this->assertSame(120.0, $summary['custo_produto_unico']);
        $this->assertSame(160.0, $summary['custo_insumos_ou_produto'] + $summary['custos_chegada_importacao']);
        $this->assertSame(40.0, $summary['custos_entrada']);
        $this->assertSame(10.0, $summary['custos_producao']);
        $this->assertSame(20.0, $summary['percentual_lucro']);
        $this->assertSame(12.0, $summary['percentual_impostos_saida']);
        $this->assertSame(15.0, $summary['percentual_saida']);
    }

    private function makeComponent(
        string $nome,
        string $categoria,
        string $tipo,
        float $valor,
    ): array {
        return [
            'nome' => $nome,
            'categoria' => $categoria,
            'tipo' => $tipo,
            'valor' => $valor,
            'obrigatorio' => false,
        ];
    }
}
