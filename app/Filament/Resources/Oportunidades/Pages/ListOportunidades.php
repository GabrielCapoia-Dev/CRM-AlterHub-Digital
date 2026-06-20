<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Filament\Resources\Oportunidades\OportunidadeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOportunidades extends ListRecords
{
    protected static string $resource = OportunidadeResource::class;

    protected static ?string $title = 'Oportunidades - Lista';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kanban')
                ->label('CRM principal')
                ->icon('heroicon-o-view-columns')
                ->url(static::getResource()::getUrl()),
            OportunidadeResource::configureCreateAction(CreateAction::make()),
        ];
    }
}
