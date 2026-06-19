<?php

namespace Tests\Feature\Produtos;

use App\Models\Produto;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\ProdutoCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoCostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_bulk_factor_configuration_and_recalculates_sale_prices(): void
    {
        $unidade = TipoUnidadeMedida::query()->create([
            'nome' => 'Unidade',
            'sigla' => 'un',
        ]);

        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-BULK-01',
            'nome' => 'Insumo base',
            'origem' => 'nacional',
            'tipo_unidade_medida_id' => $unidade->id,
            'custo_referencia' => 25.0000,
        ]);

        $produtoA = $this->createProdutoComConfiguracaoInicial('Produto A', $insumo);
        $produtoB = $this->createProdutoComConfiguracaoInicial('Produto B', $insumo);

        $updatedCount = app(ProdutoCostingService::class)->applyBulkCostConfiguration(
            Produto::query()->whereKey([$produtoA->id, $produtoB->id])->get(),
            [
                'apply_lucro_percentual' => true,
                'lucro_percentual' => '20,00',
                'apply_componentes_custo' => true,
                'produtoComponentesCusto' => [
                    [
                        'nome' => 'Operacional',
                        'categoria' => 'fator',
                        'tipo' => 'percentual',
                        'valor' => '10,00',
                        'obrigatorio' => false,
                    ],
                    [
                        'nome' => 'Frete',
                        'categoria' => 'fator',
                        'tipo' => 'valor_fixo_brl',
                        'valor' => '30,00',
                        'obrigatorio' => false,
                    ],
                ],
            ],
        );

        $this->assertSame(2, $updatedCount);

        foreach ([$produtoA, $produtoB] as $produto) {
            $produto->refresh();

            $this->assertSame('72.60', $produto->preco_tabela);
            $this->assertSame('60.50', $produto->preco_minimo);
            $this->assertCount(1, $produto->produtoInsumos);
            $this->assertCount(3, $produto->produtoComponentesCusto);
            $this->assertSame(['Operacional', 'Frete', 'Lucro desejado'], $produto->produtoComponentesCusto()->orderBy('ordem')->pluck('nome')->all());
            $this->assertSame('10.0000', $produto->produtoComponentesCusto()->orderBy('ordem')->first()->valor);
            $this->assertSame('30.0000', $produto->produtoComponentesCusto()->orderBy('ordem')->skip(1)->first()->valor);
        }
    }

    private function createProdutoComConfiguracaoInicial(string $nome, Insumo $insumo): Produto
    {
        $produto = Produto::query()->create([
            'nome' => $nome,
            'unidade_medida' => 'un',
            'status' => 'ativo',
            'preco_tabela' => 100.00,
            'preco_minimo' => 90.00,
        ]);

        $produto->produtoInsumos()->create([
            'insumo_id' => $insumo->id,
            'quantidade' => 1,
            'unidade_consumo' => 'un',
            'ordem' => 0,
            'custo_unitario_snapshot' => 25.0000,
            'custo_total_snapshot' => 25.0000,
        ]);

        $produto->produtoComponentesCusto()->create([
            'nome' => 'Comissao',
            'categoria' => 'fator',
            'tipo' => 'percentual',
            'valor' => 5.0000,
            'obrigatorio' => false,
            'ordem' => 0,
        ]);

        return $produto;
    }
}
