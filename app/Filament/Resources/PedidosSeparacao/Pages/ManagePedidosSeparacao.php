<?php

namespace App\Filament\Resources\PedidosSeparacao\Pages;

use App\Filament\Resources\PedidosSeparacao\PedidoSeparacaoResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePedidosSeparacao extends ManageRecords
{
    protected static string $resource = PedidoSeparacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
