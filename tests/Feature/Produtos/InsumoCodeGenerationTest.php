<?php

namespace Tests\Feature\Produtos;

use App\Models\Produtos\Insumo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsumoCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_internal_code_when_missing(): void
    {
        $primeiro = Insumo::create([
            'nome' => 'Enzima alfa',
        ]);

        $segundo = Insumo::create([
            'nome' => 'Reagente beta',
        ]);

        $manual = Insumo::create([
            'nome' => 'Solvente gama',
            'codigo_interno' => 'INS-MANUAL-9000',
        ]);

        $terceiroGerado = Insumo::create([
            'nome' => 'Buffer delta',
        ]);

        $this->assertSame('INS-UBT-0001', $primeiro->codigo_interno);
        $this->assertSame('INS-UBT-0002', $segundo->codigo_interno);
        $this->assertSame('INS-MANUAL-9000', $manual->codigo_interno);
        $this->assertSame('INS-UBT-0003', $terceiroGerado->codigo_interno);
    }
}
