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
            'produtoInsumos' => [
                ['insumo_id' => $insumoA->id, 'quantidade' => 2],
                ['insumo_id' => $insumoB->id, 'quantidade' => 1],
            ],
            'produtoComponentesCusto' => [
                $this->makeComponent('Frete', 'fator', 'valor_fixo_brl', 20),
                $this->makeComponent('Operacional', 'fator', 'percentual', 10),
                $this->makeComponent('Lucro desejado', 'lucro', 'percentual', 20),
            ],
        ]);

        $this->assertSame('un', $prepared['produtoInsumos'][0]['unidade_consumo']);
        $this->assertSame(100.0, $prepared['produtoInsumos'][0]['custo_unitario_snapshot']);
        $this->assertSame(200.0, $prepared['produtoInsumos'][0]['custo_total_snapshot']);
        $this->assertSame('un', $prepared['produtoInsumos'][1]['unidade_consumo']);
        $this->assertSame(50.0, $prepared['produtoInsumos'][1]['custo_unitario_snapshot']);
        $this->assertSame(50.0, $prepared['produtoInsumos'][1]['custo_total_snapshot']);
        $this->assertSame(250.0, $prepared['custo_base_formacao']);
        $this->assertSame(297.0, $prepared['preco_minimo']);
        $this->assertSame(356.4, $prepared['preco_tabela']);
        $this->assertSame(356.4, $prepared['preco_sugerido']);
        $this->assertTrue($prepared['ativo']);
    }

    public function test_it_blocks_products_without_insumos_or_product_price(): void
    {
        $service = app(ProdutoPricingCalculator::class);

        $this->expectException(ValidationException::class);

        $service->prepareForPersistence([
            'status' => 'ativo',
            'produtoInsumos' => [],
            'produtoComponentesCusto' => [
                $this->makeComponent('Frete', 'fator', 'valor_fixo_brl', 10),
            ],
        ]);
    }

    public function test_manual_product_base_price_overrides_insumo_suggestion(): void
    {
        $unidade = TipoUnidadeMedida::query()->create([
            'nome' => 'Unidade',
            'sigla' => 'un',
        ]);

        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-OVERRIDE',
            'nome' => 'Insumo override',
            'origem' => 'nacional',
            'tipo_unidade_medida_id' => $unidade->id,
            'custo_referencia' => 50.0000,
        ]);

        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence([
            'status' => 'ativo',
            'produtoInsumos' => [
                ['insumo_id' => $insumo->id, 'quantidade' => 2],
            ],
            'produtoComponentesCusto' => [
                $this->makeComponent('Custo do produto', 'custo_produto', 'valor_fixo_brl', 150),
                $this->makeComponent('Lucro desejado', 'lucro', 'percentual', 10),
            ],
        ]);

        $this->assertSame(100.0, $prepared['produtoInsumos'][0]['custo_total_snapshot']);
        $this->assertSame(150.0, $prepared['custo_base_formacao']);
        $this->assertSame(150.0, $prepared['preco_minimo']);
        $this->assertSame(165.0, $prepared['preco_tabela']);
    }

    public function test_default_components_are_empty_after_factor_simplification(): void
    {
        $componentes = collect(app(ProdutoPricingCalculator::class)->defaultComponentes())
            ->pluck('nome');

        $this->assertTrue($componentes->isEmpty());
    }

    public function test_it_calculates_single_product_with_factors_and_profit(): void
    {
        $service = app(ProdutoPricingCalculator::class);

        $prepared = $service->prepareForPersistence([
            'status' => 'ativo',
            'produtoInsumos' => [],
            'produtoComponentesCusto' => [
                $this->makeComponent('Custo do produto', 'custo_produto', 'valor_fixo_brl', 120),
                $this->makeComponent('Frete', 'fator', 'valor_fixo_brl', 30),
                $this->makeComponent('Quebra operacional', 'fator', 'percentual', 10),
                $this->makeComponent('Fator divisor', 'fator', 'percentual', 0.95),
                $this->makeComponent('Lucro desejado', 'lucro', 'percentual', 20),
            ],
        ]);

        $summary = $service->summarizeState($prepared, refreshSnapshots: false);

        $this->assertSame(120.0, $prepared['custo_base_formacao']);
        $this->assertEqualsWithDelta(173.68, $prepared['preco_minimo'], 0.01);
        $this->assertEqualsWithDelta(208.42, $prepared['preco_tabela'], 0.01);
        $this->assertSame(120.0, $summary['custo_produto_unico']);
        $this->assertSame(120.0, $summary['preco_produto']);
        $this->assertSame(30.0, $summary['soma_fixos_brl']);
        $this->assertSame(10.0, $summary['soma_percentuais']);
        $this->assertSame(0.95, $summary['fator_divisor']);
        $this->assertSame(20.0, $summary['percentual_lucro']);
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
