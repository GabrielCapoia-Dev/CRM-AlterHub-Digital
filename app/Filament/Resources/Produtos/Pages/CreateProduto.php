<?php

namespace App\Filament\Resources\Produtos\Pages;

use App\Filament\Resources\Produtos\Pages\Concerns\InteractsWithProdutoCosting;
use App\Filament\Resources\Produtos\ProdutoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduto extends CreateRecord
{
    use InteractsWithProdutoCosting;

    protected static string $resource = ProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return $this->produtoCostingActions();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareProdutoData($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
