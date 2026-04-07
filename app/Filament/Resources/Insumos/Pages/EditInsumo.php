<?php

namespace App\Filament\Resources\Insumos\Pages;

use App\Filament\Resources\Insumos\InsumoResource;
use App\Services\Produtos\InsumoCostCalculator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInsumo extends EditRecord
{
    protected static string $resource = InsumoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $prepared = app(InsumoCostCalculator::class)->prepareForPersistence($data);

        $this->data = array_replace($this->data ?? [], $prepared);
        $this->form->fill($this->data);

        return $prepared;
    }
}
