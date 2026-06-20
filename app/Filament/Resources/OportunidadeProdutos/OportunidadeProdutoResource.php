<?php

namespace App\Filament\Resources\OportunidadeProdutos;

use App\Filament\Resources\OportunidadeProdutos\Pages\ManageOportunidadeProdutos;
use App\Models\OportunidadeProduto;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class OportunidadeProdutoResource extends Resource
{
    protected static ?string $model = OportunidadeProduto::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Produto da oportunidade';

    protected static ?string $pluralModelLabel = 'Produtos da oportunidade';

    public static ?string $slug = 'oportunidade-produtos';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function formComponents(bool $withOportunidade = true): array
    {
        $components = [];

        if ($withOportunidade) {
            $components[] = Select::make('oportunidade_id')
                ->label('Oportunidade')
                ->relationship('oportunidade', 'titulo')
                ->searchable()
                ->preload()
                ->required();
        }

        $components[] = Select::make('produto_id')
            ->label('Produto')
            ->relationship('produto', 'nome')
            ->searchable()
            ->preload()
            ->required();

        $components[] = TextInput::make('preco_negociado')
            ->label('Preço negociado')
            ->numeric()
            ->rule('decimal:0,2')
            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
            ->prefix('R$')
            ->minValue(0)
            ->placeholder('0,00');

        $components[] = TextInput::make('quantidade')
            ->label('Quantidade')
            ->numeric()
            ->rule('decimal:0,4')
            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
            ->default(1)
            ->minValue(0.0001)
            ->placeholder('1,0000')
            ->required();

        $components[] = Textarea::make('observacao')
            ->label('Observação')
            ->rows(4)
            ->maxLength(2000)
            ->columnSpanFull();

        return $components;
    }

    public static function tableColumns(bool $withOportunidade = true): array
    {
        $identity = [
            TextColumn::make('produto.nome')
                ->label('Produto')
                ->searchable()
                ->sortable()
                ->weight('semibold')
                ->wrap()
                ->extraAttributes(['class' => 'crm-list-title'], merge: true),
        ];

        if ($withOportunidade) {
            array_unshift($identity, TextColumn::make('oportunidade.titulo')
                ->label('Oportunidade')
                ->searchable()
                ->sortable()
                ->toggleable()
                ->wrap()
                ->extraAttributes(['class' => 'crm-list-field'], merge: true));
        }

        return [
            Split::make([
                Stack::make($identity),

                TextColumn::make('preco_negociado')
                    ->label('Preço negociado')
                    ->description('Preço negociado', position: 'above')
                    ->money('BRL')
                    ->sortable()
                    ->placeholder('Sem valor')
                    ->alignEnd()
                    ->grow(false)
                    ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'crm-list-top']),

            TextColumn::make('quantidade')
                ->label('Quantidade')
                ->description('Quantidade', position: 'above')
                ->formatStateUsing(fn ($state): string => NumericFormat::decimal($state))
                ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

            TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->description('Atualizado em', position: 'above')
                ->dateTime('d/m/Y H:i')
                ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produto vinculado')
                    ->description('Produto de interesse registrado na negociação.')
                    ->icon(Heroicon::OutlinedTag)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(static::formComponents()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->defaultSort('updated_at', 'desc')
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeProdutos::route('/'),
        ];
    }
}
