<?php

namespace App\Filament\Resources\Produtos;

use App\Filament\Resources\Produtos\Pages\ManageProdutos;
use App\Models\Produto;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProdutoResource extends Resource
{
    protected static ?string $model = Produto::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Produtos';

    protected static ?string $modelLabel = 'Produto';

    protected static ?string $pluralModelLabel = 'Produtos';

    public static ?string $slug = 'produtos';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produto')
                    ->description('Cadastro do produto acabado comercializado pela empresa.')
                    ->icon(Heroicon::OutlinedTag)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('codigo_interno')
                            ->label('Código interno')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'produtos', column: 'codigo_interno', ignoreRecord: true)
                            ->placeholder('Ex.: UBT-QM-1000'),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: Kit diagnóstico UniBiotech'),

                        TextInput::make('unidade_medida')
                            ->label('Unidade de medida')
                            ->maxLength(50)
                            ->placeholder('Ex.: mL, kg, un'),

                        TextInput::make('preco_tabela')
                            ->label('Preço de tabela')
                            ->numeric()
                            ->prefix('R$')
                            ->minValue(0)
                            ->placeholder('0,00'),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull()
                            ->placeholder('Descreva o contexto comercial do produto.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo_interno')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Produto $record) => $record->unidade_medida ?: null),

                TextColumn::make('preco_tabela')
                    ->label('Preço tabela')
                    ->money('BRL')
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('nome')
            ->searchPlaceholder('Buscar por nome ou código...')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionados'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProdutos::route('/'),
        ];
    }
}
