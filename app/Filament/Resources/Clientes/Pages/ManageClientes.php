<?php

namespace App\Filament\Resources\Clientes\Pages;

use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Clientes\Actions\GerenciarDependenciasAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClientes extends ManageRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [

            GerenciarDependenciasAction::make(),

            CreateAction::make()
                ->modalWidth('4xl')
                ->modalHeading('Novo cliente')
                ->modalDescription('Preencha os dados fiscais, status e contato do cliente.')
                ->slideOver(false)
                ->createAnother(false),
        ];
    }
}