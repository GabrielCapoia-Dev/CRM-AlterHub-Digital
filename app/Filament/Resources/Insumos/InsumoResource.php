<?php

namespace App\Filament\Resources\Insumos;

use App\Filament\Resources\Insumos\Pages\ManageInsumos;
use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use App\Services\Produtos\InsumoCostCalculator;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InsumoResource extends Resource
{
    protected static ?string $model = Insumo::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Insumos';

    protected static ?string $modelLabel = 'Insumo';

    protected static ?string $pluralModelLabel = 'Insumos';

    protected static string | UnitEnum | null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->description('Dados principais de cadastro e classificação do insumo.')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do insumo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('codigo_interno')
                            ->label('Código interno')
                            ->maxLength(50)
                            ->unique(table: 'insumos', column: 'codigo_interno', ignoreRecord: true)
                            ->placeholder('Deixe em branco se o processo gerar automaticamente.'),

                        Select::make('tipo_insumo_id')
                            ->label('Tipo de insumo')
                            ->relationship('tipoInsumo', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status_insumo_id')
                            ->label('Status')
                            ->relationship('statusInsumo', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('ncm')
                            ->label('NCM')
                            ->maxLength(10)
                            ->placeholder('0000.00.00'),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Fornecimento e custo')
                    ->description('Configure o fornecedor, a origem do insumo e a formação do custo efetivo.')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('fornecedor_id')
                            ->label('Fornecedor preferencial')
                            ->relationship('fornecedor', 'razao_social')
                            ->getOptionLabelFromRecordUsing(
                                fn (Fornecedor $record): string => $record->nome_fantasia
                                    ? "{$record->razao_social} ({$record->nome_fantasia})"
                                    : $record->razao_social
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('origem')
                            ->label('Origem')
                            ->options(Insumo::origemOptions())
                            ->default('nacional')
                            ->required()
                            ->live(),

                        Select::make('tipo_unidade_medida_id')
                            ->label('Unidade de medida')
                            ->relationship('tipoUnidadeMedida', 'nome')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->sigla
                                ? "{$record->nome} ({$record->sigla})"
                                : $record->nome)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('tipo_armazenamento_id')
                            ->label('Tipo de armazenamento')
                            ->relationship('tipoArmazenamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('estoque_minimo')
                            ->label('Estoque mínimo')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('0,0000'),

                        TextInput::make('custo_referencia')
                            ->label('Custo unitário em BRL')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->visible(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->placeholder('0,0000'),

                        Select::make('moeda_origem')
                            ->label('Moeda de origem')
                            ->options(Insumo::moedaOptions())
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado'),

                        TextInput::make('custo_moeda_origem')
                            ->label('Custo na moeda de origem')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->live(),

                        TextInput::make('taxa_cambio')
                            ->label('Taxa de câmbio utilizada')
                            ->numeric()
                            ->minValue(0.000001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->helperText('Sempre manual. Não há valor de negócio fixo nem integração automática nesta fase.')
                            ->live(),

                        Repeater::make('insumoFatoresCusto')
                            ->relationship()
                            ->orderColumn('ordem')
                            ->columnSpanFull()
                            ->addActionLabel('Adicionar fator de custo')
                            ->collapsible()
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('tipo')
                                    ->label('Tipo')
                                    ->options(InsumoFatorCusto::tipoOptions())
                                    ->required(),

                                TextInput::make('valor')
                                    ->label('Valor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                            ]),

                        Placeholder::make('valor_convertido_brl_preview')
                            ->label('Valor convertido em BRL')
                            ->content(fn (Get $get): string => static::formatCurrency(
                                static::buildCostSummary($get)['valor_convertido_brl']
                            ))
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado'),

                        Placeholder::make('custo_nacionalizado_preview')
                            ->label('Custo nacionalizado')
                            ->content(fn (Get $get): string => static::formatCurrency(
                                static::buildCostSummary($get)['custo_nacionalizado']
                            )),

                        Placeholder::make('custo_referencia_preview')
                            ->label('Custo de referência efetivo')
                            ->content(fn (Get $get): string => static::formatCurrency(
                                static::buildCostSummary($get)['custo_referencia']
                            )),

                        Placeholder::make('percentual_total_preview')
                            ->label('Percentuais aplicados')
                            ->content(fn (Get $get): string => static::formatPercent(
                                static::buildCostSummary($get)['soma_percentuais']
                            ))
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado'),
                    ]),

                Section::make('Observações')
                    ->description('Notas internas, requisitos regulatórios ou apontamentos operacionais.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacao')
                            ->label('Observações internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fornecedor', 'tipoInsumo', 'statusInsumo', 'tipoUnidadeMedida']))
            ->columns([
                TextColumn::make('codigo_interno')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('fornecedor.razao_social')
                    ->label('Fornecedor')
                    ->searchable()
                    ->placeholder('Não informado'),

                TextColumn::make('origem')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return 'Não definida';
                        }

                        return Insumo::origemOptions()[$state] ?? $state;
                    }),

                TextColumn::make('tipoInsumo.nome')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('custo_referencia')
                    ->label('Custo efetivo')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('tipoUnidadeMedida.nome')
                    ->label('Unidade')
                    ->formatStateUsing(function (Insumo $record): string {
                        $unidade = $record->tipoUnidadeMedida;

                        if (! $unidade) {
                            return '—';
                        }

                        return $unidade->sigla
                            ? "{$unidade->nome} ({$unidade->sigla})"
                            : $unidade->nome;
                    }),

                TextColumn::make('statusInsumo.nome')
                    ->label('Status')
                    ->badge(),
            ])
            ->defaultSort('nome')
            ->searchPlaceholder('Buscar por nome, código ou NCM...')
            ->filters([
                SelectFilter::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->relationship('fornecedor', 'razao_social')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('origem')
                    ->label('Origem')
                    ->options(Insumo::origemOptions()),

                SelectFilter::make('status_insumo_id')
                    ->label('Status')
                    ->relationship('statusInsumo', 'nome')
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
            'index' => ManageInsumos::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Novo insumo')
            ->modalWidth('6xl')
            ->createAnother(false)
            ->mutateFormDataUsing(fn (array $data): array => app(InsumoCostCalculator::class)->prepareForPersistence($data));
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->modalWidth('6xl')
            ->fillForm(fn (Insumo $record): array => static::getModalFormData($record))
            ->mutateFormDataUsing(fn (array $data): array => app(InsumoCostCalculator::class)->prepareForPersistence($data));
    }

    public static function getModalFormData(Insumo $record): array
    {
        return [
            ...$record->attributesToArray(),
            'insumoFatoresCusto' => $record->insumoFatoresCusto()
                ->orderBy('ordem')
                ->get()
                ->map(fn (InsumoFatorCusto $item): array => [
                    'id' => $item->id,
                    'nome' => $item->nome,
                    'tipo' => $item->tipo,
                    'valor' => (float) $item->valor,
                    'ordem' => $item->ordem,
                ])
                ->all(),
        ];
    }

    protected static function buildCostSummary(Get $get): array
    {
        return app(InsumoCostCalculator::class)->summarizeFromState([
            'origem' => $get('origem'),
            'custo_referencia' => $get('custo_referencia'),
            'custo_moeda_origem' => $get('custo_moeda_origem'),
            'taxa_cambio' => $get('taxa_cambio'),
            'insumoFatoresCusto' => $get('insumoFatoresCusto') ?? [],
        ]);
    }

    protected static function formatCurrency(float|int|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    protected static function formatPercent(float|int|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }
}
