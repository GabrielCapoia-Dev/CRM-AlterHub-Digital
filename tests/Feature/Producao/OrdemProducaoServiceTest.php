<?php

namespace Tests\Feature\Producao;

use App\Enum\ProdutoClassificacao;
use App\Enum\StatusOrdemProducao;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Services\Producao\OrdemProducaoService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrdemProducaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_production_cycle_snapshots_bom_and_updates_reserved_and_physical_stock(): void
    {
        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-PROD-01',
            'nome' => 'Materia prima',
            'origem' => 'nacional',
            'custo_referencia' => 5,
        ]);
        app(MovimentacaoEstoqueService::class)->createForInsumo([
            'insumo_id' => $insumo->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'realizado_em' => now(),
        ]);

        $produto = Produto::query()->create([
            'codigo_interno' => 'PRD-FAB-01',
            'nome' => 'Produto fabricado',
            'status' => 'ativo',
            'classificacao' => ProdutoClassificacao::Fabricado,
            'origem' => 'nacional',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 15,
        ]);
        $bom = $produto->produtoInsumos()->create([
            'insumo_id' => $insumo->id,
            'quantidade' => 3,
            'unidade_consumo' => 'kg',
            'ordem' => 0,
            'custo_unitario_snapshot' => 5,
            'custo_total_snapshot' => 15,
        ]);

        $service = app(OrdemProducaoService::class);
        $ordem = $service->criar([
            'produto_id' => $produto->id,
            'quantidade_planejada' => 2,
        ]);

        $this->assertSame(StatusOrdemProducao::Planejada, $ordem->status);
        $this->assertSame(6.0, (float) $ordem->insumos->first()->quantidade_necessaria_snapshot);
        $this->assertSame(30.0, (float) $ordem->custo_total_planejado_snapshot);

        $bom->update(['quantidade' => 99, 'custo_unitario_snapshot' => 99]);
        $this->assertSame(6.0, (float) $ordem->fresh('insumos')->insumos->first()->quantidade_necessaria_snapshot);
        $this->assertSame(5.0, (float) $ordem->insumos->first()->custo_unitario_snapshot);

        $ordem = $service->reservarInsumos($ordem);
        $this->assertSame(StatusOrdemProducao::Reservada, $ordem->status);
        $this->assertSame(6.0, (float) $insumo->fresh()->estoque_reservado);
        $this->assertSame(4.0, $insumo->fresh()->estoqueDisponivel());

        $ordem = $service->reservarInsumos($ordem);
        $this->assertSame(6.0, (float) $insumo->fresh()->estoque_reservado);

        $ordem = $service->liberarInsumos($ordem);
        $this->assertSame(StatusOrdemProducao::Planejada, $ordem->status);
        $this->assertSame(0.0, (float) $insumo->fresh()->estoque_reservado);

        $ordem = $service->reservarInsumos($ordem);
        try {
            $service->darEntradaProdutoAcabado($ordem, 0);
            $this->fail('Quantidade produzida zero deveria reverter consumo e entrada.');
        } catch (ValidationException) {
            $this->assertSame(StatusOrdemProducao::Reservada, $ordem->fresh()->status);
            $this->assertSame(10.0, (float) $insumo->fresh()->estoque_fisico);
            $this->assertSame(6.0, (float) $insumo->fresh()->estoque_reservado);
        }

        $ordem = $service->darEntradaProdutoAcabado($ordem);
        $this->assertSame(StatusOrdemProducao::Concluida, $ordem->status);
        $this->assertSame(4.0, (float) $insumo->fresh()->estoque_fisico);
        $this->assertSame(0.0, (float) $insumo->fresh()->estoque_reservado);
        $this->assertSame('ordem_producao', $ordem->insumos->first()->insumoMovimentacao->origem_tipo);
        $this->assertSame(2.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame('ordem_producao', $ordem->produtoMovimentacao->origem_tipo);

        $movimentacoesInsumo = $insumo->insumoMovimentacoes()->count();
        $movimentacoesProduto = $produto->produtoMovimentacoes()->count();
        $ordem = $service->darEntradaProdutoAcabado($ordem);

        $this->assertSame(4.0, (float) $insumo->fresh()->estoque_fisico);
        $this->assertSame(2.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame($movimentacoesInsumo, $insumo->insumoMovimentacoes()->count());
        $this->assertSame($movimentacoesProduto, $produto->produtoMovimentacoes()->count());
    }

    public function test_it_rejects_resale_products_and_insufficient_input_stock(): void
    {
        $revenda = Produto::query()->create([
            'nome' => 'Produto de revenda',
            'status' => 'ativo',
            'classificacao' => 'revenda',
            'origem' => 'nacional',
        ]);

        try {
            app(OrdemProducaoService::class)->criar([
                'produto_id' => $revenda->id,
                'quantidade_planejada' => 1,
            ]);
            $this->fail('Produto de revenda nao deveria gerar ordem de producao.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('produto_id', $exception->errors());
        }

        $insumo = Insumo::query()->create([
            'nome' => 'Insumo sem saldo',
            'origem' => 'nacional',
            'custo_referencia' => 1,
        ]);
        $fabricado = Produto::query()->create([
            'nome' => 'Fabricado sem saldo',
            'status' => 'ativo',
            'classificacao' => 'fabricado',
            'origem' => 'nacional',
        ]);
        $fabricado->produtoInsumos()->create([
            'insumo_id' => $insumo->id,
            'quantidade' => 1,
            'custo_unitario_snapshot' => 1,
            'custo_total_snapshot' => 1,
        ]);
        $ordem = app(OrdemProducaoService::class)->criar([
            'produto_id' => $fabricado->id,
            'quantidade_planejada' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(OrdemProducaoService::class)->reservarInsumos($ordem);
    }
}
