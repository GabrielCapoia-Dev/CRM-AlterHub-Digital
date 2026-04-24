<?php

namespace App\Filament\Resources\DespesasOperacionais\Pages;

use App\Filament\Resources\DespesasOperacionais\DespesaOperacionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDespesasOperacionais extends ManageRecords
{
    protected static string $resource = DespesaOperacionalResource::class;

    protected string $view = 'filament.resources.despesas-operacionais.pages.manage-despesas-operacionais';

    protected function getHeaderActions(): array
    {
        return [
            DespesaOperacionalResource::configureCreateAction(CreateAction::make()),
        ];
    }
}
