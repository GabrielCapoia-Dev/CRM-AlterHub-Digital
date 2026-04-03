<?php

namespace App\Filament\Resources\Oportunidades\RelationManagers;

use App\Filament\Resources\OportunidadeTarefas\OportunidadeTarefaResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OportunidadeTarefasRelationManager extends RelationManager
{
    protected static string $relationship = 'oportunidadeTarefas';

    protected static ?string $relatedResource = OportunidadeTarefaResource::class;

    protected static ?string $title = 'Tarefas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tarefa')
                    ->columns(2)
                    ->schema(OportunidadeTarefaResource::formComponents(withOportunidade: false)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(OportunidadeTarefaResource::tableColumns(withOportunidade: false));
    }
}
