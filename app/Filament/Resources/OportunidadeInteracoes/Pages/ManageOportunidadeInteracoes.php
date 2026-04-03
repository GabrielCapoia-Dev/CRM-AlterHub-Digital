<?php

namespace App\Filament\Resources\OportunidadeInteracoes\Pages;

use App\Filament\Resources\OportunidadeInteracoes\OportunidadeInteracaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOportunidadeInteracoes extends ManageRecords
{
    protected static string $resource = OportunidadeInteracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('4xl')
                ->modalHeading('Nova interação')
                ->createAnother(false),
        ];
    }
}
