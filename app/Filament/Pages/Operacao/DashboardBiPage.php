<?php

namespace App\Filament\Pages\Operacao;

use App\Enum\PermissoesEnum;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class DashboardBiPage extends BaseOperacaoPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Dashboard BI';

    protected static ?string $title = 'Dashboard BI';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'operacao/dashboard-bi';

    protected string $view = 'filament.pages.operacao.dashboard-bi';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(PermissoesEnum::ListarDashboardBI->value) ?? false;
    }

    public function getSnapshotProperty(): array
    {
        return $this->analytics()->getDashboardSnapshot($this->filters());
    }
}
