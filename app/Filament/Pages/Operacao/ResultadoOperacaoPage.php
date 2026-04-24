<?php

namespace App\Filament\Pages\Operacao;

use App\Enum\PermissoesEnum;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ResultadoOperacaoPage extends BaseOperacaoPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Resultado (DRE)';

    protected static ?string $title = 'Resultado (DRE)';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'operacao/resultado';

    protected string $view = 'filament.pages.operacao.resultado-operacao';

    public string $detailView = 'receita';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(PermissoesEnum::ListarResultadoOperacao->value) ?? false;
    }

    public function showDetail(string $detailView): void
    {
        $this->detailView = $detailView;
    }

    public function getSnapshotProperty(): array
    {
        return $this->analytics()->getDreSnapshot($this->filters());
    }

    public function getDetailRowsProperty(): array
    {
        if (! ($this->snapshot['ok'] ?? false)) {
            return [];
        }

        return match ($this->detailView) {
            'cmv' => $this->snapshot['cmv_por_produto'],
            'despesas' => $this->snapshot['despesas'],
            default => $this->snapshot['vendas'],
        };
    }
}
