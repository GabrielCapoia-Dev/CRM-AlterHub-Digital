<?php

namespace Tests\Feature;

use App\Filament\Pages\Operacao\DashboardBiPage;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\InsumoMovimentacoes\InsumoMovimentacaoResource;
use App\Filament\Resources\ProdutoMovimentacoes\ProdutoMovimentacaoResource;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    public function test_sales_and_stock_movements_are_registered_in_navigation(): void
    {
        $this->assertTrue(VendaOperacaoResource::shouldRegisterNavigation());
        $this->assertSame(
            ClienteResource::getNavigationGroup(),
            VendaOperacaoResource::getNavigationGroup(),
        );
        $this->assertSame(4, VendaOperacaoResource::getNavigationSort());

        $this->assertTrue(ProdutoMovimentacaoResource::shouldRegisterNavigation());
        $this->assertSame(
            'Produtos',
            ProdutoMovimentacaoResource::getNavigationParentItem(),
        );

        $this->assertTrue(InsumoMovimentacaoResource::shouldRegisterNavigation());
        $this->assertSame(
            'Insumos',
            InsumoMovimentacaoResource::getNavigationParentItem(),
        );
    }

    public function test_dashboard_pages_do_not_render_the_cluster_breadcrumb(): void
    {
        $this->assertSame([], (new DashboardBiPage)->getBreadcrumbs());
    }
}
