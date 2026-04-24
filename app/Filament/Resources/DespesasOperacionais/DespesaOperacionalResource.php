<?php

namespace App\Filament\Resources\DespesasOperacionais;

use App\Filament\Resources\DespesasOperacionais\Pages\ManageDespesasOperacionais;
use App\Models\DespesaOperacional;
use App\Models\Produto;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DespesaOperacionalResource extends Resource
{
    protected static ?string $model = DespesaOperacional::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Despesas';

    protected static ?string $modelLabel = 'Despesa operacional';

    protected static ?string $pluralModelLabel = 'Despesas operacionais';

    public static ?string $slug = 'operacao/despesas';

    protected static string|UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Despesa')
                    ->description('Cadastro unico de despesas que alimentam o resultado e os indicadores operacionais.')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('data_competencia')
                            ->label('Data')
                            ->default(now())
                            ->required(),

                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(DespesaOperacional::tipoOptions())
                            ->default('fixa')
                            ->required(),

                        TextInput::make('descricao')
                            ->label('Descricao')
                            ->maxLength(255)
                            ->required()
                            ->columnSpanFull(),

                        Select::make('categoria')
                            ->label('Categoria')
                            ->options(DespesaOperacional::categoriaOptions())
                            ->searchable()
                            ->required(),

                        TextInput::make('subcategoria')
                            ->label('Subcategoria')
                            ->maxLength(255),

                        TextInput::make('valor')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$')
                            ->minValue(0.01)
                            ->required(),

                        Select::make('produto_id')
                            ->label('Produto vinculado')
                            ->relationship('produto', 'nome')
                            ->getOptionLabelFromRecordUsing(fn (Produto $record): string => $record->codigo_interno
                                ? "{$record->codigo_interno} - {$record->nome}"
                                : $record->nome)
                            ->searchable()
                            ->preload()
                            ->placeholder('Sem produto'),

                        Textarea::make('observacao')
                            ->label('Observacao')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data_competencia')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('descricao')
                    ->label('Descricao')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (DespesaOperacional $record): ?string => $record->subcategoria ?: null),

                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DespesaOperacional::categoriaOptions()[$state] ?? $state),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'fixa' ? 'info' : 'warning')
                    ->formatStateUsing(fn (string $state): string => DespesaOperacional::tipoOptions()[$state] ?? $state),

                TextColumn::make('produto.nome')
                    ->label('Produto')
                    ->searchable()
                    ->placeholder('Geral')
                    ->description(fn (DespesaOperacional $record): ?string => $record->produto?->codigo_interno),

                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('data_competencia', 'desc')
            ->searchPlaceholder('Buscar por descricao, subcategoria ou produto...')
            ->filters([
                SelectFilter::make('ano_referencia')
                    ->label('Ano')
                    ->options(collect(range(now()->year, now()->year - 5))->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])->all()),

                SelectFilter::make('mes_referencia')
                    ->label('Mes')
                    ->options([
                        '1' => '01',
                        '2' => '02',
                        '3' => '03',
                        '4' => '04',
                        '5' => '05',
                        '6' => '06',
                        '7' => '07',
                        '8' => '08',
                        '9' => '09',
                        '10' => '10',
                        '11' => '11',
                        '12' => '12',
                    ]),

                SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(DespesaOperacional::categoriaOptions()),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(DespesaOperacional::tipoOptions()),

                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->relationship('produto', 'nome')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                static::configureEditAction(EditAction::make()->label('Editar')),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDespesasOperacionais::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Nova despesa')
            ->modalWidth('4xl')
            ->slideOver(false)
            ->createAnother(false)
            ->modalHeading('Nova despesa')
            ->modalDescription('Registre despesas operacionais e tributarias com o mesmo padrao visual do painel.')
            ->modalSubmitActionLabel('Salvar despesa')
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-expense-modal',
            ])
            ->mutateDataUsing(fn (array $data): array => [
                ...$data,
                'user_id' => auth()->id(),
            ]);
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->modalWidth('4xl')
            ->slideOver(false)
            ->modalHeading('Editar despesa')
            ->modalDescription('Atualize classificacao, valor e vinculo do lancamento.')
            ->modalSubmitActionLabel('Salvar alteracoes')
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-expense-modal',
            ])
            ->mutateDataUsing(fn (array $data): array => [
                ...$data,
                'user_id' => auth()->id(),
            ]);
    }
}
