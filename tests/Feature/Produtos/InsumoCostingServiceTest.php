<?php

namespace Tests\Feature\Produtos;

use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\InsumoCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsumoCostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_bulk_cost_configuration_for_imported_insumos(): void
    {
        $fornecedor = $this->createFornecedor();

        $insumoA = Insumo::query()->create([
            'nome' => 'Insumo importado A',
            'origem' => 'nacional',
            'custo_referencia' => 10.0000,
        ]);

        $insumoB = Insumo::query()->create([
            'nome' => 'Insumo importado B',
            'origem' => 'nacional',
            'custo_referencia' => 12.0000,
        ]);

        $insumoA->insumoFatoresCusto()->create([
            'nome' => 'Fator antigo',
            'tipo' => 'valor_fixo_brl',
            'valor' => 1.0000,
            'ordem' => 0,
        ]);

        $updatedCount = app(InsumoCostingService::class)->applyBulkCostConfiguration(
            Insumo::query()->whereKey([$insumoA->id, $insumoB->id])->get(),
            [
                'apply_fornecedor_id' => true,
                'fornecedor_id' => $fornecedor->uuid,
                'apply_cost_context' => true,
                'origem' => 'importado',
                'moeda_origem' => 'USD',
                'custo_moeda_origem' => '12,40',
                'taxa_cambio' => '5,200000',
                'apply_fatores_custo' => true,
                'insumoFatoresCusto' => [
                    [
                        'nome' => 'Frete internacional',
                        'tipo' => 'valor_fixo_brl',
                        'valor' => '10,00',
                    ],
                    [
                        'nome' => 'Imposto',
                        'tipo' => 'percentual',
                        'valor' => '8,50',
                    ],
                ],
            ],
        );

        $this->assertSame(2, $updatedCount);

        foreach ([$insumoA, $insumoB] as $insumo) {
            $insumo->refresh();

            $this->assertSame($fornecedor->uuid, $insumo->fornecedor_id);
            $this->assertSame('importado', $insumo->origem);
            $this->assertSame('USD', $insumo->moeda_origem);
            $this->assertSame('12.4000', $insumo->custo_moeda_origem);
            $this->assertSame('5.200000', $insumo->taxa_cambio);
            $this->assertSame('64.4800', $insumo->valor_convertido_brl);
            $this->assertSame('80.8108', $insumo->custo_nacionalizado);
            $this->assertSame('80.8108', $insumo->custo_referencia);
            $this->assertSame(['Frete internacional', 'Imposto'], $insumo->insumoFatoresCusto()->orderBy('ordem')->pluck('nome')->all());
        }
    }

    private function createFornecedor(): Fornecedor
    {
        $categoriaId = DB::table('categorias_fornecimento')->insertGetId([
            'nome' => 'Quimicos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statusId = DB::table('status_homologacao')->insertGetId([
            'nome' => 'Homologado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prazoId = DB::table('prazos_pagamento')->insertGetId([
            'nome' => '30 dias',
            'dias' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $formaId = DB::table('formas_pagamento')->insertGetId([
            'nome' => 'Boleto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Fornecedor::query()->create([
            'codigo_interno' => 'FOR-BULK-001',
            'id_categoria_fornecimento' => $categoriaId,
            'id_status_homologacao' => $statusId,
            'id_prazo_pagamento' => $prazoId,
            'id_forma_pagamento' => $formaId,
            'razao_social' => 'Fornecedor Internacional Teste',
            'cnpj' => '45.723.174/0001-10',
            'nome_completo' => 'Contato Fornecedor',
        ]);
    }
}
