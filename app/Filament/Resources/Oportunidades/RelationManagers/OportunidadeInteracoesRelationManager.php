<?php

namespace App\Filament\Resources\Oportunidades\RelationManagers;

use App\Filament\Resources\OportunidadeInteracoes\OportunidadeInteracaoResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OportunidadeInteracoesRelationManager extends RelationManager
{
    protected static string $relationship = 'oportunidadeInteracoes';

    protected static ?string $relatedResource = OportunidadeInteracaoResource::class;

    protected static ?string $title = 'Interações';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Interação')
                    ->columns(2)
                    ->schema(OportunidadeInteracaoResource::formComponents(withOportunidade: false)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(OportunidadeInteracaoResource::tableColumns(withOportunidade: false));
    }
}
