<?php

namespace App\Filament\Resources\OrdensProducao\Pages;

use App\Filament\Resources\OrdensProducao\OrdemProducaoResource;
use App\Services\Producao\OrdemProducaoService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrdensProducao extends ManageRecords
{
    protected static string $resource = OrdemProducaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova ordem')
                ->modalWidth('4xl')
                ->createAnother(false)
                ->using(fn (array $data) => app(OrdemProducaoService::class)->criar($data, auth()->user())),
        ];
    }
}
