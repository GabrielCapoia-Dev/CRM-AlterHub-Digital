<?php

namespace App\Filament\Resources\VendasOperacao\Pages;

use App\Filament\Exports\Actions\CrmExportActions;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVendasOperacao extends ManageRecords
{
    protected static string $resource = VendaOperacaoResource::class;

    protected string $view = 'filament.resources.vendas-operacao.pages.manage-vendas-operacao';

    protected function getHeaderActions(): array
    {
        return [
            VendaOperacaoResource::configureCreateAction(CreateAction::make()),
            CrmExportActions::vendas(),
        ];
    }
}
