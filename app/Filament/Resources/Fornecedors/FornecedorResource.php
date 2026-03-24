<?php

namespace App\Filament\Resources\Fornecedors;

use App\Filament\Resources\Fornecedors\Pages\ManageFornecedors;
use App\Models\Empresas\Fornecedor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class FornecedorResource extends Resource
{
    protected static ?string $model = Fornecedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Dados da Empresa')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('razao_social')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('nome_fantasia')
                            ->maxLength(255),

                        TextInput::make('cnpj')
                            ->required()
                            ->mask('99.999.999/9999-99')
                            ->unique(ignoreRecord: true),

                        TextInput::make('inscricao_estadual'),

                        TextInput::make('codigo_interno'),
                    ]),

                Section::make('Relacionamentos')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('id_categoria_fornecimento')
                            ->relationship('categoriaFornecimento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('id_status_homologacao')
                            ->relationship('statusHomologacao', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('id_prazo_pagamento')
                            ->relationship('prazoPagamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('id_forma_pagamento')
                            ->relationship('formaPagamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Contato')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome_completo')->required(),
                        TextInput::make('cargo'),
                        TextInput::make('email')->email(),
                        TextInput::make('telefone'),
                    ]),

                Section::make('Endereço')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('cep'),
                        TextInput::make('uf')->maxLength(2),
                        TextInput::make('cidade'),

                        TextInput::make('logradouro')->columnSpan(2),
                        TextInput::make('numero'),
                        TextInput::make('complemento'),
                        TextInput::make('bairro'),
                    ]),

                Section::make('Observações')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacoes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razao_social')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cnpj')
                    ->searchable(),

                TextColumn::make('categoriaFornecimento.nome')
                    ->label('Categoria')
                    ->sortable(),

                TextColumn::make('statusHomologacao.nome')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('cidade'),

                TextColumn::make('telefone'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFornecedors::route('/'),
        ];
    }
}
