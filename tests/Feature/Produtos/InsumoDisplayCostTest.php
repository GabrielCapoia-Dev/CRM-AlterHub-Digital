<?php

namespace Tests\Feature\Produtos;

use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use Tests\TestCase;

class InsumoDisplayCostTest extends TestCase
{
    public function test_imported_insumo_exposes_converted_cost_and_recalculated_final_cost(): void
    {
        $insumo = new Insumo([
            'origem' => 'importado',
            'custo_referencia' => 38.1500,
            'custo_moeda_origem' => 7.0000,
            'taxa_cambio' => 5.450000,
            'valor_convertido_brl' => 38.1500,
            'custo_nacionalizado' => 38.1500,
        ]);

        $insumo->setRelation('insumoFatoresCusto', collect([
            new InsumoFatorCusto([
                'nome' => 'Taxa Fiduciaria',
                'tipo' => 'percentual',
                'valor' => 0.8900,
                'ordem' => 1,
            ]),
        ]));

        $this->assertSame(38.15, $insumo->effectiveCostAmount());
        $this->assertSame(42.8652, $insumo->finalCostAmount());
    }
}
