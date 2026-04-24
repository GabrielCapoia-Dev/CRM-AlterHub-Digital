<?php

namespace App\Filament\Resources\InsumoMovimentacoes\Pages;

use App\Filament\Resources\InsumoMovimentacoes\InsumoMovimentacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsumoMovimentacoes extends ManageRecords
{
    protected static string $resource = InsumoMovimentacaoResource::class;

    protected string $view = 'filament.resources.insumo-movimentacoes.pages.manage-insumo-movimentacoes';

    protected function getHeaderActions(): array
    {
        return [
            InsumoMovimentacaoResource::configureCreateAction(CreateAction::make()),
        ];
    }
}
