<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login;
use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Auth\Pages\ResetPassword;
use App\Filament\Clusters\VendasCluster;
use App\Filament\Pages\Operacao\DashboardBiPage;
use App\Filament\Resources\Oportunidades\OportunidadeResource;
use App\Filament\Resources\PedidosSeparacao\PedidoSeparacaoResource;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use App\Http\Middleware\EnsureRequiredPasswordChange;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PainelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('painel')
            ->default()
            ->path('painel')
            ->brandName('Unibiotech')
            ->brandLogo(asset('images/unibiotech-logo.svg'))
            ->brandLogoHeight('2rem')
            ->login()
            ->passwordResetRoutePrefix('recuperar-senha')
            ->passwordResetRequestRouteSlug('solicitar')
            ->passwordResetRouteSlug('redefinir')
            ->profile()
            ->databaseNotifications()
            ->globalSearch(false)
            ->spa()
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                // Primary → Navy Blue (#17368D) — cor principal do design system
                'primary' => [
                    50 => '234, 241, 253', // #EAF1FD
                    100 => '208, 224, 250', // #D0E0FA
                    200 => '168, 197, 245', // #A8C5F5
                    300 => '126, 168, 240', // #7EA8F0
                    400 => '90,  139, 230', // #5A8BE6
                    500 => '58,  109, 214', // #3A6DD6
                    600 => '42,  88,  192', // #2A58C0
                    700 => '30,  69,  168', // #1E45A8
                    800 => '23,  54,  141', // #17368D — brand
                    900 => '17, 25, 44',    // #11192c
                    950 => '10,  26,  74',  // #0A1A4A
                ],
                // Gray → Neutral do design system
                'gray' => [
                    50 => '244, 245, 249', // #F4F5F9
                    100 => '232, 235, 242', // #E8EBF2
                    200 => '212, 216, 230', // #D4D8E6
                    300 => '180, 186, 206', // #B4BACE
                    400 => '144, 152, 176', // #9098B0
                    500 => '107, 118, 148', // #6B7694
                    600 => '71,  81,  110', // #47516E
                    700 => '45,  55,  86',  // #2D3756
                    800 => '26,  35,  64',  // #1A2340
                    900 => '15,  23,  41',  // #0F1729
                    950 => '10,  13,  20',  // #0A0D14
                ],
                // Danger → Error do design system (#E53E6B)
                'danger' => [
                    50 => '254, 242, 242',
                    100 => '254, 226, 226',
                    200 => '252, 186, 186',
                    300 => '248, 113, 113',
                    400 => '245, 62,  107', // #E53E6B
                    500 => '220, 38,  38',
                    600 => '185, 28,  28',
                    700 => '153, 27,  27',
                    800 => '127, 29,  29',
                    900 => '69,  10,  10',
                    950 => '45,  5,   5',
                ],
                // Success → #00C97B
                'success' => [
                    50 => '236, 253, 245',
                    100 => '209, 250, 229',
                    200 => '167, 243, 208',
                    300 => '110, 231, 183',
                    400 => '52,  211, 153',
                    500 => '0,   201, 123', // #00C97B
                    600 => '5,   150, 105',
                    700 => '4,   120, 87',
                    800 => '6,   95,  70',
                    900 => '4,   78,  56',
                    950 => '2,   44,  34',
                ],
                // Warning → #F5A623
                'warning' => [
                    50 => '255, 251, 235',
                    100 => '254, 243, 199',
                    200 => '253, 230, 138',
                    300 => '252, 211, 77',
                    400 => '251, 191, 36',
                    500 => '245, 166, 35',  // #F5A623
                    600 => '217, 119, 6',
                    700 => '180, 83,  9',
                    800 => '146, 64,  14',
                    900 => '120, 53,  15',
                    950 => '69,  26,  3',
                ],
                // Info → #3A6DD6 (primary-500)
                'info' => [
                    50 => '239, 246, 255',
                    100 => '219, 234, 254',
                    200 => '191, 219, 254',
                    300 => '147, 197, 253',
                    400 => '96,  165, 250',
                    500 => '58,  109, 214', // #3A6DD6
                    600 => '37,  99,  235',
                    700 => '29,  78,  216',
                    800 => '30,  64,  175',
                    900 => '30,  58,  138',
                    950 => '23,  37,  84',
                ],
            ])
            ->font('Plus Jakarta Sans')
            ->homeUrl(fn (): string => DashboardBiPage::canAccess()
                ? DashboardBiPage::getUrl()
                : OportunidadeResource::getUrl('index'))
            ->navigationGroups([
                'Operação',
                'Acesso',
                'Estoque',
            ])
            ->navigationItems([
                NavigationItem::make('Vendas')
                    ->icon('heroicon-o-shopping-cart')
                    ->group(VendasCluster::getNavigationGroup())
                    ->sort(4)
                    ->url(fn (): string => VendaOperacaoResource::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.painel.operacao.*'))
                    ->visible(fn (): bool => VendaOperacaoResource::canAccess()),
            ])
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->resources([
                PedidoSeparacaoResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
                EnsureRequiredPasswordChange::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.partials.crm-list-table-styles')
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): View => view('filament.partials.document-settings-button')
            )
            ->plugins([
                AuthDesignerPlugin::make()
                    ->defaults(
                        fn (AuthPageConfig $config): AuthPageConfig => $config
                            ->media(
                                'https://unibiotechbrasil.com.br/wp-content/uploads/2021/07/leite-fermentado-2.jpg',
                                'Ingredientes e produtos lácteos da Unibiotech',
                            )
                            ->mediaPosition(MediaPosition::Left)
                            ->mediaSize('56%')
                            ->renderHook(
                                AuthDesignerRenderHook::MediaOverlay,
                                fn (): View => view('filament.auth.media-branding'),
                            )
                    )
                    ->login(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->usingPage(Login::class))
                    ->passwordReset(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->usingPage(RequestPasswordReset::class)
                        ->usingResetPage(ResetPassword::class)),
            ]);
    }
}
