<?php

namespace App\Filament\Resources\Romaneios\Pages;

use App\Filament\Resources\Romaneios\RomaneioResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRomaneios extends ManageRecords
{
    protected static string $resource = RomaneioResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
