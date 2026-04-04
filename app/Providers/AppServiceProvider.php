<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        FilamentAsset::register([
            Css::make('geral', secure_asset('css/geral.css?v=' . filemtime(public_path('css/geral.css')))),
            Css::make('crm-kanban', secure_asset('css/crm-kanban.css?v=' . filemtime(public_path('css/crm-kanban.css')))),
            Js::make('crm-kanban', secure_asset('js/crm-kanban.js?v=' . filemtime(public_path('js/crm-kanban.js')))),
        ]);
    }
}
