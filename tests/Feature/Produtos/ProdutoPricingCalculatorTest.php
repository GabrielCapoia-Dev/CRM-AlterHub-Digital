<?php

namespace Tests\Feature\Produtos;

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
        $insumoA = Insumo::query()->create([
            'codigo_interno' => 'INS-A',
            'nome' => 'Insumo A',
            'origem' => 'nacional',
            'custo_referencia' => 100.0000,
        ]);

        $insumoB = Insumo::query()->create([
            'codigo_interno' => 'INS-B',
            'nome' => 'Insumo B',
            'origem' => 'nacional',
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
                $this->makeComponent('Comissão', 'comerciais', 'percentual_sobre_venda', 5),
                $this->makeComponent('Margem', 'comerciais', 'percentual_sobre_venda', 30, true),
                $this->makeComponent('Frete', 'custos_fixos', 'valor_fixo_brl', 20),
            ],
        ]);

        $this->assertSame(100.0, $prepared['produtoInsumos'][0]['custo_unitario_snapshot']);
        $this->assertSame(200.0, $prepared['produtoInsumos'][0]['custo_total_snapshot']);
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
                $this->makeComponent('Comissão', 'comerciais', 'percentual_sobre_venda', 40),
                $this->makeComponent('Margem', 'comerciais', 'percentual_sobre_venda', 60, true),
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

    private function makeComponent(
        string $nome,
        string $categoria,
        string $tipo,
        float $valor,
        bool $isMargem = false,
    ): array {
        return [
            'nome' => $nome,
            'categoria' => $categoria,
            'tipo' => $tipo,
            'valor' => $valor,
            'obrigatorio' => false,
            'is_margem' => $isMargem,
        ];
    }
}
