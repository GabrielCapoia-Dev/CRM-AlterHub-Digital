<?php

namespace Tests\Feature\Produtos;

use App\Models\Produto;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\ProdutoCostingService;
use App\Services\Produtos\ProdutoPricingCalculator;
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

    public function test_it_saves_fractional_insumo_quantities_with_four_decimal_places(): void
    {
        $unidade = TipoUnidadeMedida::query()->create([
            'nome' => 'Quilograma',
            'sigla' => 'KG',
        ]);

        $sal = $this->createInsumo('INS-SAL-01', 'Sal leve refinado', $unidade, 0.9500);
        $benzoato = $this->createInsumo('INS-BEN-01', 'Benzoato de sodio', $unidade, 11.0000);

        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence([
            'nome' => 'Produto fracionado',
            'unidade_medida' => 'KG',
            'status' => 'ativo',
            'produtoInsumos' => [
                ['insumo_id' => $sal->id, 'quantidade' => '0,265'],
                ['insumo_id' => $benzoato->id, 'quantidade' => '0,015'],
            ],
            'produtoComponentesCusto' => [],
        ]);

        $produto = app(ProdutoCostingService::class)->savePreparedProduct(new Produto(), $prepared);
        $quantidades = $produto->produtoInsumos()->orderBy('ordem')->pluck('quantidade')->all();

        $this->assertSame(['0.2650', '0.0150'], $quantidades);
        $this->assertSame('0.2518', $produto->produtoInsumos()->orderBy('ordem')->first()->custo_total_snapshot);
    }

    public function test_it_syncs_updated_removed_and_added_insumo_links_when_saving_product(): void
    {
        $unidade = TipoUnidadeMedida::query()->create([
            'nome' => 'Unidade',
            'sigla' => 'un',
        ]);

        $insumoA = $this->createInsumo('INS-SYNC-A', 'Insumo A', $unidade, 10.0000);
        $insumoB = $this->createInsumo('INS-SYNC-B', 'Insumo B', $unidade, 20.0000);
        $insumoC = $this->createInsumo('INS-SYNC-C', 'Insumo C', $unidade, 30.0000);

        $produto = $this->createProdutoComConfiguracaoInicial('Produto sync', $insumoA);
        $keptLink = $produto->produtoInsumos()->where('insumo_id', $insumoA->id)->firstOrFail();
        $removedLink = $produto->produtoInsumos()->create([
            'insumo_id' => $insumoB->id,
            'quantidade' => 2,
            'unidade_consumo' => 'un',
            'ordem' => 1,
            'custo_unitario_snapshot' => 20.0000,
            'custo_total_snapshot' => 40.0000,
        ]);

        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence([
            'nome' => 'Produto sync editado',
            'unidade_medida' => 'un',
            'status' => 'ativo',
            'produtoInsumos' => [
                ['id' => $keptLink->id, 'insumo_id' => $insumoA->id, 'quantidade' => '0,750'],
                ['insumo_id' => $insumoC->id, 'quantidade' => '0,125'],
            ],
            'produtoComponentesCusto' => [],
        ]);

        $produto = app(ProdutoCostingService::class)->savePreparedProduct($produto, $prepared);
        $links = $produto->produtoInsumos()->orderBy('ordem')->get();

        $this->assertDatabaseMissing('produto_insumos', ['id' => $removedLink->id]);
        $this->assertCount(2, $links);
        $this->assertSame($keptLink->id, $links[0]->id);
        $this->assertSame('0.7500', $links[0]->quantidade);
        $this->assertSame($insumoC->id, $links[1]->insumo_id);
        $this->assertSame('0.1250', $links[1]->quantidade);
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

    private function createInsumo(string $codigo, string $nome, TipoUnidadeMedida $unidade, float $custoReferencia): Insumo
    {
        return Insumo::query()->create([
            'codigo_interno' => $codigo,
            'nome' => $nome,
            'origem' => 'nacional',
            'tipo_unidade_medida_id' => $unidade->id,
            'custo_referencia' => $custoReferencia,
        ]);
    }
}
