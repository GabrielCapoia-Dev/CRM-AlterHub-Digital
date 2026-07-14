<?php

namespace App\Filament\Resources\Transportadoras\Pages;

use App\Filament\Resources\Transportadoras\TransportadoraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTransportadoras extends ManageRecords
{
    protected static string $resource = TransportadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova transportadora')
                ->modalWidth('6xl')
                ->createAnother(false),
        ];
    }
}
