<?php

namespace App\Filament\Pages\Operacao;

use App\Enum\PermissoesEnum;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class VisaoConsolidadaPage extends BaseOperacaoPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Visao consolidada';

    protected static ?string $title = 'Visao consolidada';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'operacao/visao-consolidada';

    protected string $view = 'filament.pages.operacao.visao-consolidada';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->redirect(DashboardBiPage::getUrl());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(PermissoesEnum::ListarVisaoConsolidadaOperacao->value) ?? false;
    }

    public function getSnapshotProperty(): array
    {
        return $this->analytics()->getVisaoConsolidadaSnapshot($this->filters());
    }
}
