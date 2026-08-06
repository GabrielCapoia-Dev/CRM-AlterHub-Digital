<?php

namespace App\Providers;

use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\DespesaOperacional;
use App\Models\DocumentoConfiguracao;
use App\Models\Empresas\Fornecedor;
use App\Models\OrdemProducao;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Models\RegraTributaria;
use App\Models\Romaneio;
use App\Models\Transportadora;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Models\VendaPedidoFoto;
use App\Policies\ClientePolicy;
use App\Policies\DespesaOperacionalPolicy;
use App\Policies\DocumentoConfiguracaoPolicy;
use App\Policies\FornecedorPolicy;
use App\Policies\InsumoPolicy;
use App\Policies\OrdemProducaoPolicy;
use App\Policies\ProdutoPolicy;
use App\Policies\RegraTributariaPolicy;
use App\Policies\RolePolicy;
use App\Policies\RomaneioPolicy;
use App\Policies\TransportadoraPolicy;
use App\Policies\VendaOperacaoLotePolicy;
use App\Policies\VendaOperacaoPedidoPolicy;
use App\Policies\VendaOperacaoPolicy;
use App\Policies\VendaPedidoFotoPolicy;
use App\Policies\UserPolicy;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (config('crm.force_https')) {
            URL::forceScheme('https');
        }

        Gate::policy(Produto::class, ProdutoPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Insumo::class, InsumoPolicy::class);
        Gate::policy(DespesaOperacional::class, DespesaOperacionalPolicy::class);
        Gate::policy(DocumentoConfiguracao::class, DocumentoConfiguracaoPolicy::class);
        Gate::policy(VendaOperacao::class, VendaOperacaoPolicy::class);
        Gate::policy(VendaOperacaoLote::class, VendaOperacaoLotePolicy::class);
        Gate::policy(VendaOperacaoPedido::class, VendaOperacaoPedidoPolicy::class);
        Gate::policy(VendaPedidoFoto::class, VendaPedidoFotoPolicy::class);
        Gate::policy(Romaneio::class, RomaneioPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Fornecedor::class, FornecedorPolicy::class);
        Gate::policy(OrdemProducao::class, OrdemProducaoPolicy::class);
        Gate::policy(RegraTributaria::class, RegraTributariaPolicy::class);
        Gate::policy(Transportadora::class, TransportadoraPolicy::class);

        FilamentAsset::register([
            Css::make('geral', asset('css/geral.css?v='.filemtime(public_path('css/geral.css')))),
            Css::make('crm-kanban', asset('css/crm-kanban.css?v='.filemtime(public_path('css/crm-kanban.css')))),
            Css::make('operacao-analytics', asset('css/operacao-analytics.css?v='.filemtime(public_path('css/operacao-analytics.css')))),
            Js::make('crm-kanban', asset('js/crm-kanban.js?v='.filemtime(public_path('js/crm-kanban.js')))),
        ]);
    }
}
