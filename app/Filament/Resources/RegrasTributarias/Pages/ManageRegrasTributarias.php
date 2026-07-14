<?php

namespace App\Filament\Resources\RegrasTributarias\Pages;

use App\Filament\Resources\RegrasTributarias\RegraTributariaResource;
use App\Services\Fiscal\RegraTributariaService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegrasTributarias extends ManageRecords
{
    protected static string $resource = RegraTributariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova versao')
                ->modalWidth('5xl')
                ->createAnother(false)
                ->using(fn (array $data) => app(RegraTributariaService::class)->criarVersao($data)),
        ];
    }
}
