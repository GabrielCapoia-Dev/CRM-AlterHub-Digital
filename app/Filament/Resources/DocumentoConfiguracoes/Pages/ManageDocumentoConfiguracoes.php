<?php

namespace App\Filament\Resources\DocumentoConfiguracoes\Pages;

use App\Filament\Resources\DocumentoConfiguracoes\DocumentoConfiguracaoResource;
use App\Models\DocumentoConfiguracao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentoConfiguracoes extends ManageRecords
{
    protected static string $resource = DocumentoConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Configurar documentos')
                ->visible(fn (): bool => DocumentoConfiguracao::query()
                    ->where('chave', DocumentoConfiguracao::CHAVE_PADRAO)
                    ->doesntExist()),
        ];
    }
}
