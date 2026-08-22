<?php

namespace Tests\Feature\Produtos;

use App\Enum\PermissoesEnum;
use App\Filament\Pages\Estoque\AcompanhamentoEstoque;
use App\Models\Acesso\User;
use App\Models\InsumoMovimentacao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Produtos\Insumo;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Produtos\EstoqueAcompanhamentoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcompanhamentoEstoquePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_query_orders_and_paginates_both_ledgers_in_the_database(): void
    {
        [$produto, $insumo] = $this->createItems();
        $sale = $this->createSaleLine($produto);

        $oldProductEntry = $this->createProductMovement($produto, [
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'documento_referencia' => 'NF-ANTIGA',
            'impacto_estoque' => 10,
            'saldo_anterior' => 0,
            'saldo_atual' => 10,
            'realizado_em' => '2026-08-20 08:00:00',
        ]);

        $saleOutput = $this->createProductMovement($produto, [
            'tipo' => 'saida',
            'origem_tipo' => 'venda',
            'origem_id' => $sale->id,
            'documento_referencia' => 'DOC-VENDA-FALLBACK',
            'motivo' => 'Venda confirmada',
            'impacto_estoque' => -2,
            'saldo_anterior' => 10,
            'saldo_atual' => 8,
            'realizado_em' => '2026-08-21 10:00:00',
        ]);

        $inputEntry = $this->createInputMovement($insumo, [
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'documento_referencia' => 'NF-RECENTE',
            'impacto_estoque' => 20,
            'saldo_anterior' => 0,
            'saldo_atual' => 20,
            'realizado_em' => '2026-08-22 09:00:00',
        ]);

        $service = app(EstoqueAcompanhamentoService::class);
        $types = [
            EstoqueAcompanhamentoService::ITEM_PRODUTO,
            EstoqueAcompanhamentoService::ITEM_INSUMO,
        ];

        $firstPage = $service->paginateMovements([], $types, perPage: 2, page: 1);
        $secondPage = $service->paginateMovements([], $types, perPage: 2, page: 2);

        $this->assertSame(3, $firstPage->total());
        $this->assertSame(2, $firstPage->lastPage());
        $this->assertSame(
            [
                "insumo:{$inputEntry->id}",
                "produto:{$saleOutput->id}",
            ],
            collect($firstPage->items())
                ->map(fn (object $row): string => "{$row->item_tipo}:{$row->movimento_id}")
                ->all(),
        );
        $this->assertSame(
            ["produto:{$oldProductEntry->id}"],
            collect($secondPage->items())
                ->map(fn (object $row): string => "{$row->item_tipo}:{$row->movimento_id}")
                ->all(),
        );
    }

    public function test_filters_by_item_type_movement_origin_and_period_and_resolves_sale_order(): void
    {
        [$produto, $insumo] = $this->createItems();
        $sale = $this->createSaleLine($produto, 'VOP-00991');

        $this->createInputMovement($insumo, [
            'tipo' => 'saida',
            'origem_tipo' => 'ordem_producao',
            'motivo' => 'Consumo na produção',
            'impacto_estoque' => -1,
            'saldo_anterior' => 20,
            'saldo_atual' => 19,
            'realizado_em' => '2026-08-21 08:00:00',
        ]);

        $this->createProductMovement($produto, [
            'tipo' => 'saida',
            'origem_tipo' => 'venda',
            'origem_id' => $sale->id,
            'documento_referencia' => 'VENDA-REF',
            'motivo' => 'Venda confirmada',
            'impacto_estoque' => -2,
            'saldo_anterior' => 10,
            'saldo_atual' => 8,
            'realizado_em' => '2026-08-22 10:00:00',
        ]);

        $service = app(EstoqueAcompanhamentoService::class);
        $results = $service->paginateMovements([
            'item_type' => 'produto',
            'item_search' => 'prd-monitor',
            'movement_type' => 'saida',
            'origin_type' => 'venda',
            'date_from' => '2026-08-22',
            'date_to' => '2026-08-22',
        ], ['produto', 'insumo']);

        $this->assertSame(1, $results->total());
        $movement = $results->getCollection()->first();
        $this->assertNotNull($movement);
        $this->assertSame('Produto monitorado', $movement->nome);
        $this->assertSame('VOP-00991', $movement->venda_documento);

        $summary = $service->summary([
            'origin_type' => 'venda',
        ], ['produto', 'insumo']);

        $this->assertSame(0, $summary['entradas']);
        $this->assertSame(1, $summary['saidas']);
        $this->assertSame(1, $summary['saidas_venda']);
    }

    public function test_date_range_includes_both_days_and_excludes_the_next_day_boundary(): void
    {
        [$produto, $insumo] = $this->createItems();

        $this->createProductMovement($produto, [
            'documento_referencia' => 'ANTES-DO-INTERVALO',
            'realizado_em' => '2026-08-20 23:59:59',
        ]);
        $this->createProductMovement($produto, [
            'documento_referencia' => 'INICIO-DO-INTERVALO',
            'realizado_em' => '2026-08-21 00:00:00',
        ]);
        $this->createInputMovement($insumo, [
            'documento_referencia' => 'FIM-DO-INTERVALO',
            'realizado_em' => '2026-08-22 23:59:59',
        ]);
        $this->createInputMovement($insumo, [
            'documento_referencia' => 'FORA-NO-DIA-SEGUINTE',
            'realizado_em' => '2026-08-23 00:00:00',
        ]);

        $results = app(EstoqueAcompanhamentoService::class)->paginateMovements([
            'date_from' => '2026-08-21',
            'date_to' => '2026-08-22',
        ], ['produto', 'insumo']);

        $this->assertSame(2, $results->total());
        $this->assertSame(
            ['FIM-DO-INTERVALO', 'INICIO-DO-INTERVALO'],
            $results->getCollection()->pluck('documento_referencia')->all(),
        );
    }

    public function test_page_accepts_either_permission_and_does_not_leak_the_other_domain(): void
    {
        [$produto, $insumo] = $this->createItems();
        $this->createProductMovement($produto, [
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'impacto_estoque' => 10,
            'saldo_anterior' => 0,
            'saldo_atual' => 10,
            'realizado_em' => '2026-08-21 08:00:00',
        ]);
        $this->createInputMovement($insumo, [
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'impacto_estoque' => 20,
            'saldo_anterior' => 0,
            'saldo_atual' => 20,
            'realizado_em' => '2026-08-22 08:00:00',
        ]);

        $this->registerStockPermissions();
        $productViewer = User::factory()->create(['email_approved' => true]);
        $productViewer->givePermissionTo(PermissoesEnum::ListarProdutosCRM->value);

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $this->actingAs($productViewer);

        $this->assertTrue(AcompanhamentoEstoque::canAccess());
        $this->assertStringEndsWith('/painel/acompanhamento-estoque', AcompanhamentoEstoque::getUrl());
        $this->get(AcompanhamentoEstoque::getUrl())
            ->assertOk()
            ->assertSeeText('Produto monitorado')
            ->assertDontSeeText('Insumo monitorado')
            ->assertSeeText('Posição atual dos itens')
            ->assertSeeText('Timeline de movimentações');

        $inputViewer = User::factory()->create(['email_approved' => true]);
        $inputViewer->givePermissionTo(PermissoesEnum::ListarInsumos->value);
        $this->actingAs($inputViewer);

        $this->assertTrue(AcompanhamentoEstoque::canAccess());
        $this->get(AcompanhamentoEstoque::getUrl())
            ->assertOk()
            ->assertSeeText('Insumo monitorado')
            ->assertDontSeeText('Produto monitorado');
    }

    public function test_page_uses_responsive_filament_pagination_for_items_and_movements(): void
    {
        [$produto] = $this->createItems();

        for ($index = 1; $index <= 13; $index++) {
            Produto::query()->create([
                'codigo_interno' => sprintf('PRD-PAG-%02d', $index),
                'nome' => sprintf('Produto paginação %02d', $index),
                'unidade_medida' => 'un',
                'estoque_fisico' => $index,
                'estoque_reservado' => 0,
                'estoque_minimo' => 1,
            ]);
        }

        for ($index = 1; $index <= 21; $index++) {
            $this->createProductMovement($produto, [
                'documento_referencia' => sprintf('MOV-PAG-%02d', $index),
                'realizado_em' => now()->subMinutes($index),
            ]);
        }

        $this->registerStockPermissions();
        $viewer = User::factory()->create(['email_approved' => true]);
        $viewer->givePermissionTo(PermissoesEnum::ListarProdutosCRM->value);

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $this->actingAs($viewer);

        $response = $this->get(AcompanhamentoEstoque::getUrl())->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('wire:key="stock-items-pagination"', $html);
        $this->assertStringContainsString('wire:key="stock-movements-pagination"', $html);
        $this->assertStringContainsString('fi-pagination-item-icon', $html);
        $this->assertStringContainsString('itemsPage', $html);
        $this->assertStringContainsString('movementsPage', $html);
        $this->assertStringNotContainsString('Pagination Navigation', $html);
        $this->assertStringNotContainsString('class="w-5 h-5"', $html);
    }

    /** @return array{Produto, Insumo} */
    private function createItems(): array
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PRD-MONITOR',
            'nome' => 'Produto monitorado',
            'unidade_medida' => 'un',
            'estoque_fisico' => 8,
            'estoque_reservado' => 1,
            'estoque_minimo' => 3,
        ]);

        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-MONITOR',
            'nome' => 'Insumo monitorado',
            'estoque_fisico' => 20,
            'estoque_reservado' => 2,
            'estoque_minimo' => 5,
        ]);

        return [$produto, $insumo];
    }

    private function createSaleLine(Produto $produto, string $code = 'VOP-00990'): VendaOperacao
    {
        $pedido = VendaOperacaoPedido::query()->create([
            'codigo' => $code,
            'status' => VendaOperacaoPedido::STATUS_CONFIRMADA,
            'data_venda' => '2026-08-22',
            'cliente_nome_snapshot' => 'Cliente da venda',
        ]);

        return VendaOperacao::query()->create([
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'produto_codigo_snapshot' => $produto->codigo_interno,
            'produto_nome_snapshot' => $produto->nome,
            'unidade_snapshot' => 'un',
            'data_venda' => '2026-08-22',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'receita_bruta' => 20,
            'custo_unitario_snapshot' => 4,
            'custo_total_snapshot' => 8,
            'receita_liquida' => 20,
            'lucro_bruto' => 12,
            'lucro_apos_impostos' => 12,
            'cliente_nome' => 'Cliente da venda',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createProductMovement(Produto $produto, array $overrides): ProdutoMovimentacao
    {
        return ProdutoMovimentacao::query()->create([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'quantidade' => abs((float) ($overrides['impacto_estoque'] ?? 1)),
            'impacto_estoque' => 1,
            'saldo_anterior' => 0,
            'saldo_atual' => 1,
            'unidade' => 'un',
            'realizado_em' => now(),
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createInputMovement(Insumo $insumo, array $overrides): InsumoMovimentacao
    {
        return InsumoMovimentacao::query()->create([
            'insumo_id' => $insumo->id,
            'tipo' => 'entrada',
            'origem_tipo' => 'manual',
            'quantidade' => abs((float) ($overrides['impacto_estoque'] ?? 1)),
            'impacto_estoque' => 1,
            'saldo_anterior' => 0,
            'saldo_atual' => 1,
            'unidade' => 'kg',
            'realizado_em' => now(),
            ...$overrides,
        ]);
    }

    private function registerStockPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissoesEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
    }
}
