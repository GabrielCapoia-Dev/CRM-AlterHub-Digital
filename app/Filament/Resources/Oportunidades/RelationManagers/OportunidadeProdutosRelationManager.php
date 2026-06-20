<?php

namespace App\Filament\Resources\Oportunidades\RelationManagers;

use App\Filament\Resources\OportunidadeProdutos\OportunidadeProdutoResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OportunidadeProdutosRelationManager extends RelationManager
{
    protected static string $relationship = 'oportunidadeProdutos';

    protected static ?string $relatedResource = OportunidadeProdutoResource::class;

    protected static ?string $title = 'Produtos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produto vinculado')
                    ->columns(2)
                    ->schema(OportunidadeProdutoResource::formComponents(withOportunidade: false)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(OportunidadeProdutoResource::tableColumns(withOportunidade: false))
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm');
    }
}
