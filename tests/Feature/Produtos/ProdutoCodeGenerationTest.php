<?php

namespace Tests\Feature\Produtos;

use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_internal_code_when_missing(): void
    {
        $primeiro = Produto::query()->create([
            'nome' => 'Produto alfa',
        ]);

        $segundo = Produto::query()->create([
            'nome' => 'Produto beta',
        ]);

        $manual = Produto::query()->create([
            'nome' => 'Produto gama',
            'codigo_interno' => 'PRD-MANUAL-9000',
        ]);

        $terceiroGerado = Produto::query()->create([
            'nome' => 'Produto delta',
        ]);

        $this->assertSame('PRD-UBT-0001', $primeiro->codigo_interno);
        $this->assertSame('PRD-UBT-0002', $segundo->codigo_interno);
        $this->assertSame('PRD-MANUAL-9000', $manual->codigo_interno);
        $this->assertSame('PRD-UBT-0003', $terceiroGerado->codigo_interno);
        $this->assertSame('ativo', $primeiro->status);
    }
}
