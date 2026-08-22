<?php

namespace App\Filament\Resources\Oportunidades\Pages;

use App\Filament\Resources\Oportunidades\OportunidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOportunidade extends EditRecord
{
    protected static string $resource = OportunidadeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return OportunidadeResource::prepareOpportunityDataForFill($data, $this->getRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return OportunidadeResource::prepareOpportunityDataForPersistence($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
