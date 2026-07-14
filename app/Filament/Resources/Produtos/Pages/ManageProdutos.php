<?php

namespace App\Filament\Resources\Produtos\Pages;

use App\Filament\Exports\Actions\CrmExportActions;
use App\Filament\Resources\Produtos\ProdutoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProdutos extends ManageRecords
{
    protected static string $resource = ProdutoResource::class;

    protected string $view = 'filament.resources.produtos.pages.manage-produtos';

    protected function getHeaderActions(): array
    {
        return [
            ProdutoResource::configureCreateAction(CreateAction::make()),
            CrmExportActions::produtos(),
            CrmExportActions::estoqueAtual(),
        ];
    }
}
