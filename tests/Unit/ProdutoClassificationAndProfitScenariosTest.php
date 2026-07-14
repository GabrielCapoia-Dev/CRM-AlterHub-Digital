<?php

namespace Tests\Unit;

use App\Enum\ProdutoClassificacao;
use App\Enum\ProdutoOrigem;
use App\Filament\Resources\Produtos\ProdutoResource;
use PHPUnit\Framework\TestCase;

class ProdutoClassificationAndProfitScenariosTest extends TestCase
{
    public function test_product_classification_and_origin_options_are_stable(): void
    {
        $this->assertSame([
            'revenda' => 'Revenda',
            'fabricado' => 'Fabricado',
        ], ProdutoClassificacao::options());
        $this->assertSame([
            'nacional' => 'Nacional',
            'importado' => 'Importado',
        ], ProdutoOrigem::options());
    }

    public function test_product_resource_calculates_requested_profit_scenarios(): void
    {
        $this->assertSame(110.0, ProdutoResource::profitScenarioAmount(100, 10));
        $this->assertSame(120.0, ProdutoResource::profitScenarioAmount(100, 20));
        $this->assertSame(130.0, ProdutoResource::profitScenarioAmount(100, 30));
        $this->assertSame(140.0, ProdutoResource::profitScenarioAmount(100, 40));
    }
}
