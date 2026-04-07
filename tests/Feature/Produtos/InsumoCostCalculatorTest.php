<?php

namespace Tests\Feature\Produtos;

use App\Services\Produtos\InsumoCostCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InsumoCostCalculatorTest extends TestCase
{
    public function test_it_keeps_national_cost_as_effective_reference(): void
    {
        $service = app(InsumoCostCalculator::class);

        $prepared = $service->prepareForPersistence([
            'fornecedor_id' => 'fornecedor-teste',
            'origem' => 'nacional',
            'custo_referencia' => 125.5000,
            'insumoFatoresCusto' => [
                ['nome' => 'Despesa aduaneira', 'tipo' => 'percentual', 'valor' => 10],
            ],
        ]);

        $this->assertSame(125.5, $prepared['valor_convertido_brl']);
        $this->assertSame(125.5, $prepared['custo_nacionalizado']);
        $this->assertSame(125.5, $prepared['custo_referencia']);
        $this->assertNull($prepared['moeda_origem']);
        $this->assertNull($prepared['taxa_cambio']);
        $this->assertSame([], $prepared['insumoFatoresCusto']);
    }

    public function test_it_calculates_imported_cost_with_fixed_and_percentual_factors(): void
    {
        $service = app(InsumoCostCalculator::class);

        $prepared = $service->prepareForPersistence([
            'fornecedor_id' => 'fornecedor-teste',
            'origem' => 'importado',
            'moeda_origem' => 'USD',
            'custo_moeda_origem' => 10.0000,
            'taxa_cambio' => 5.000000,
            'insumoFatoresCusto' => [
                ['nome' => 'Frete internacional', 'tipo' => 'valor_fixo_brl', 'valor' => 2.0000],
                ['nome' => 'Despesa aduaneira', 'tipo' => 'percentual', 'valor' => 10.0000],
            ],
        ]);

        $this->assertSame(50.0, $prepared['valor_convertido_brl']);
        $this->assertSame(57.2, $prepared['custo_nacionalizado']);
        $this->assertSame(57.2, $prepared['custo_referencia']);
    }

    public function test_it_requires_manual_exchange_rate_for_imported_insumos(): void
    {
        $service = app(InsumoCostCalculator::class);

        $this->expectException(ValidationException::class);

        $service->prepareForPersistence([
            'fornecedor_id' => 'fornecedor-teste',
            'origem' => 'importado',
            'moeda_origem' => 'USD',
            'custo_moeda_origem' => 10.0000,
            'taxa_cambio' => null,
        ]);
    }
}
