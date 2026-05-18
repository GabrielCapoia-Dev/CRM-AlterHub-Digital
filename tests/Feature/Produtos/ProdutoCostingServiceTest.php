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

    public function test_it_applies_bulk_cost_configuration_and_persists_localized_minimum_price(): void
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
                'apply_preco_tabela' => true,
                'preco_tabela' => '1.250,90',
                'apply_preco_minimo' => true,
                'preco_minimo' => '1.100,45',
                'apply_componentes_custo' => true,
                'produtoComponentesCusto' => [
                    [
                        'nome' => 'ICMS',
                        'categoria' => 'impostos',
                        'tipo' => 'percentual_sobre_venda',
                        'valor' => '12,50',
                        'obrigatorio' => true,
                    ],
                    [
                        'nome' => 'Frete',
                        'categoria' => 'custos_fixos',
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

            $this->assertSame('1250.90', $produto->preco_tabela);
            $this->assertSame('1100.45', $produto->preco_minimo);
            $this->assertCount(1, $produto->produtoInsumos);
            $this->assertCount(2, $produto->produtoComponentesCusto);
            $this->assertSame(['ICMS', 'Frete'], $produto->produtoComponentesCusto()->orderBy('ordem')->pluck('nome')->all());
            $this->assertSame('12.5000', $produto->produtoComponentesCusto()->orderBy('ordem')->first()->valor);
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
            'categoria' => 'comerciais',
            'tipo' => 'percentual_sobre_venda',
            'valor' => 5.0000,
            'obrigatorio' => false,
            'ordem' => 0,
        ]);

        return $produto;
    }
}
