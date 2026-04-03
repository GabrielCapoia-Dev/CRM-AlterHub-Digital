<?php

namespace App\Filament\Resources\Oportunidades\RelationManagers;

use App\Filament\Resources\OportunidadeMovimentacoes\OportunidadeMovimentacaoResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OportunidadeMovimentacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'oportunidadeMovimentacoes';

    protected static ?string $relatedResource = OportunidadeMovimentacaoResource::class;

    protected static ?string $title = 'Movimentações';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimentação')
                    ->columns(2)
                    ->schema(OportunidadeMovimentacaoResource::formComponents(withOportunidade: false)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(OportunidadeMovimentacaoResource::tableColumns(withOportunidade: false));
    }
}
