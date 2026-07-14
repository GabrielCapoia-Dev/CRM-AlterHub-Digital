<?php

namespace App\Filament\Pages\Operacao;

use App\Enum\PermissoesEnum;
use App\Filament\Clusters\AcompanhamentoCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class DashboardBiPage extends BaseOperacaoPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $cluster = AcompanhamentoCluster::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'visao-geral';

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
