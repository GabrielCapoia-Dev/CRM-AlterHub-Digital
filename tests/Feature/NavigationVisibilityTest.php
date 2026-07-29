<?php

namespace Tests\Feature;

use App\Filament\Clusters\VendasCluster;
use App\Filament\Pages\Operacao\DashboardBiPage;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\DocumentoConfiguracoes\DocumentoConfiguracaoResource;
use App\Filament\Resources\InsumoMovimentacoes\InsumoMovimentacaoResource;
use App\Filament\Resources\PedidosSeparacao\PedidoSeparacaoResource;
use App\Filament\Resources\ProdutoMovimentacoes\ProdutoMovimentacaoResource;
use App\Filament\Resources\Romaneios\RomaneioResource;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use Filament\Facades\Filament;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    public function test_sales_and_stock_movements_are_registered_in_navigation(): void
    {
        $this->assertSame(
            ClienteResource::getNavigationGroup(),
            VendasCluster::getNavigationGroup(),
        );
        $this->assertSame(4, VendasCluster::getNavigationSort());

        $this->assertTrue(VendaOperacaoResource::shouldRegisterNavigation());
        $this->assertSame(VendasCluster::class, VendaOperacaoResource::getCluster());
        $this->assertSame(VendasCluster::class, PedidoSeparacaoResource::getCluster());
        $this->assertSame(VendasCluster::class, RomaneioResource::getCluster());
        $this->assertSame(1, VendaOperacaoResource::getNavigationSort());

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

    public function test_document_settings_use_the_topbar_shortcut_and_global_search_is_disabled(): void
    {
        $this->assertFalse(DocumentoConfiguracaoResource::shouldRegisterNavigation());
        $this->assertNull(Filament::getPanel('painel')->getGlobalSearchProvider());
    }
}
