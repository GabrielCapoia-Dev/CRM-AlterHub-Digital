<?php

namespace App\Filament\Resources\Insumos\Pages;

use App\Filament\Resources\Insumos\Actions\GerenciarDependenciasAction;
use App\Filament\Resources\Insumos\InsumoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsumos extends ManageRecords
{
    protected static string $resource = InsumoResource::class;

    protected string $view = 'filament.resources.insumos.pages.manage-insumos';

    protected function getHeaderActions(): array
    {
        return [
            GerenciarDependenciasAction::make(),
            InsumoResource::configureCreateAction(CreateAction::make()),
        ];
    }
}
