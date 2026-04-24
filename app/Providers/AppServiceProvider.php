<?php

namespace App\Providers;

use App\Models\Produto;
use App\Models\DespesaOperacional;
use App\Models\VendaOperacao;
use App\Models\Produtos\Insumo;
use App\Policies\DespesaOperacionalPolicy;
use App\Policies\InsumoPolicy;
use App\Policies\ProdutoPolicy;
use App\Policies\VendaOperacaoPolicy;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(Produto::class, ProdutoPolicy::class);
        Gate::policy(Insumo::class, InsumoPolicy::class);
        Gate::policy(DespesaOperacional::class, DespesaOperacionalPolicy::class);
        Gate::policy(VendaOperacao::class, VendaOperacaoPolicy::class);

        FilamentAsset::register([
            Css::make('geral', secure_asset('css/geral.css?v=' . filemtime(public_path('css/geral.css')))),
            Css::make('crm-kanban', secure_asset('css/crm-kanban.css?v=' . filemtime(public_path('css/crm-kanban.css')))),
            Css::make('operacao-analytics', secure_asset('css/operacao-analytics.css?v=' . filemtime(public_path('css/operacao-analytics.css')))),
            Js::make('crm-kanban', secure_asset('js/crm-kanban.js?v=' . filemtime(public_path('js/crm-kanban.js')))),
        ]);
    }
}
