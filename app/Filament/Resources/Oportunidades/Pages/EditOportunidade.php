<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Filament\Resources\Oportunidades\OportunidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOportunidade extends EditRecord
{
    protected static string $resource = OportunidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
