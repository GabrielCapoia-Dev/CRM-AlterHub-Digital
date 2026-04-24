<?php

namespace App\Filament\Resources\ProdutoMovimentacoes\Pages;

use App\Filament\Resources\ProdutoMovimentacoes\ProdutoMovimentacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProdutoMovimentacoes extends ManageRecords
{
    protected static string $resource = ProdutoMovimentacaoResource::class;

    protected string $view = 'filament.resources.produto-movimentacoes.pages.manage-produto-movimentacoes';

    protected function getHeaderActions(): array
    {
        return [
            ProdutoMovimentacaoResource::configureCreateAction(CreateAction::make()),
        ];
    }
}
