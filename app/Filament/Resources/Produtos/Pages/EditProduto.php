<?php

namespace App\Filament\Resources\Produtos\Pages;

use App\Filament\Resources\Produtos\Pages\Concerns\InteractsWithProdutoCosting;
use App\Filament\Resources\Produtos\ProdutoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduto extends EditRecord
{
    use InteractsWithProdutoCosting;

    protected static string $resource = ProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->produtoCostingActions(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareProdutoData($data);
    }
}
