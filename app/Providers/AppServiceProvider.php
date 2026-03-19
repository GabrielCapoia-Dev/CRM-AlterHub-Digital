<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FilamentAsset::register([

            Css::make('geral', asset('css/geral.css')),
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
