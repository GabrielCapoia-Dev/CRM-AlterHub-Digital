<?php

namespace App\Filament\Resources\Etapas\Pages;

use App\Filament\Resources\Etapas\EtapaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEtapas extends ManageRecords
{
    protected static string $resource = EtapaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('3xl')
                ->modalHeading('Nova etapa')
                ->modalDescription('Defina a etapa e sua ordenação no funil comercial.')
                ->createAnother(false),
        ];
    }
}
