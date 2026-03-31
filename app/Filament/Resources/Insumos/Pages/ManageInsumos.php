<?php

namespace App\Filament\Resources\Insumos\Pages;

use App\Filament\Resources\Insumos\InsumoResource;
use App\Filament\Resources\Insumos\Actions\GerenciarDependenciasAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsumos extends ManageRecords
{
    protected static string $resource = InsumoResource::class;

    protected function getHeaderActions(): array
    {
        return [

            GerenciarDependenciasAction::make(),

            CreateAction::make()
                ->modalWidth('4xl')
                ->modalHeading('Novo insumo')
                ->modalDescription('Preencha a identificação, dados técnicos e condições de armazenamento do insumo.')
                ->slideOver(false)
                ->createAnother(false),
        ];
    }
}