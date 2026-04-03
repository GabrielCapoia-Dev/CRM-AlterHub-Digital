<?php

namespace App\Filament\Resources\OportunidadeProdutos;

use App\Filament\Resources\OportunidadeProdutos\Pages\ManageOportunidadeProdutos;
use App\Models\OportunidadeProduto;
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
use Filament\Tables\Columns\TextColumn;
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
            ->prefix('R$')
            ->minValue(0)
            ->placeholder('0,00');

        $components[] = Textarea::make('observacao')
            ->label('Observação')
            ->rows(4)
            ->maxLength(2000)
            ->columnSpanFull();

        return $components;
    }

    public static function tableColumns(bool $withOportunidade = true): array
    {
        $columns = [];

        if ($withOportunidade) {
            $columns[] = TextColumn::make('oportunidade.titulo')
                ->label('Oportunidade')
                ->searchable()
                ->sortable()
                ->toggleable();
        }

        $columns[] = TextColumn::make('produto.nome')
            ->label('Produto')
            ->searchable()
            ->sortable()
            ->weight('semibold');

        $columns[] = TextColumn::make('preco_negociado')
            ->label('Preço negociado')
            ->money('BRL')
            ->sortable()
            ->placeholder('Sem valor');

        $columns[] = TextColumn::make('updated_at')
            ->label('Atualizado em')
            ->dateTime('d/m/Y H:i');

        return $columns;
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
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeProdutos::route('/'),
        ];
    }
}
