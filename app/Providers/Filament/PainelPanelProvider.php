<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\Authenticate;
use App\Livewire\LoginPage;
use App\Services\UserService;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LaudoArquivoController;
use Filament\Actions\Action as GlobalAction;
use App\Models\User;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;


class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('painel')
            ->path('painel')
            ->login()
            ->profile()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Painel/Resources'), for: 'App\\Filament\\Painel\\Resources')
            ->discoverPages(in: app_path('Filament/Painel/Pages'), for: 'App\\Filament\\Painel\\Pages')
            ->discoverClusters(in: app_path('Filament/Painel/Clusters'), for: 'App\\Filament\\Painel\\Clusters')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Painel/Widgets'), for: 'App\\Filament\\Painel\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                AuthDesignerPlugin::make()
                    ->login(
                        fn(AuthPageConfig $config) => $config
                            ->media(asset('images/background.png'))
                            ->mediaPosition(MediaPosition::Left)
                            ->renderHook(AuthDesignerRenderHook::MediaOverlay, fn() => view('background-page'))
                            ->usingPage(LoginPage::class)
                            ->mediaSize('70%')
                            ->themeToggle()

                    )
                    ->profile(
                        fn($config) => $config
                            ->media(asset('images/background.png'))
                            ->mediaPosition(MediaPosition::Cover)
                    )
            ]);
    }
}