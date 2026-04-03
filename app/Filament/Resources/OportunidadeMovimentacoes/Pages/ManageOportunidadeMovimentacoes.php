<?php

namespace App\Filament\Resources\OportunidadeMovimentacoes\Pages;

use App\Filament\Resources\OportunidadeMovimentacoes\OportunidadeMovimentacaoResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOportunidadeMovimentacoes extends ManageRecords
{
    protected static string $resource = OportunidadeMovimentacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
