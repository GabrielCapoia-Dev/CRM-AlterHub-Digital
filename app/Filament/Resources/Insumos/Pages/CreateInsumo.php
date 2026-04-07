<?php

namespace App\Filament\Resources\Insumos\Pages;

use App\Filament\Resources\Insumos\InsumoResource;
use App\Services\Produtos\InsumoCostCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreateInsumo extends CreateRecord
{
    protected static string $resource = InsumoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prepared = app(InsumoCostCalculator::class)->prepareForPersistence($data);

        $this->data = array_replace($this->data ?? [], $prepared);
        $this->form->fill($this->data);

        return $prepared;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
