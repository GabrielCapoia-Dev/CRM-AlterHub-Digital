<?php

namespace App\Filament\Resources\OportunidadeProdutos\Pages;

use App\Filament\Resources\OportunidadeProdutos\OportunidadeProdutoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOportunidadeProdutos extends ManageRecords
{
    protected static string $resource = OportunidadeProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('4xl')
                ->modalHeading('Novo produto da oportunidade')
                ->createAnother(false),
        ];
    }
}
