<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Filament\Resources\Oportunidades\OportunidadeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOportunidade extends CreateRecord
{
    protected static string $resource = OportunidadeResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
