<?php

namespace App\Filament\Resources\OportunidadeTarefas\Pages;

use App\Filament\Resources\OportunidadeTarefas\OportunidadeTarefaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOportunidadeTarefas extends ManageRecords
{
    protected static string $resource = OportunidadeTarefaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('4xl')
                ->modalHeading('Nova tarefa')
                ->createAnother(false),
        ];
    }
}
