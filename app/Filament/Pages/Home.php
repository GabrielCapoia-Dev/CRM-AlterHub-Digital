<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Operacao\DashboardBiPage;
use App\Filament\Resources\Oportunidades\OportunidadeResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Home extends Page
{
    protected string $view = 'filament.pages.home';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $navigationLabel = 'Início';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->redirect(DashboardBiPage::canAccess()
            ? DashboardBiPage::getUrl()
            : OportunidadeResource::getUrl('index'));
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
