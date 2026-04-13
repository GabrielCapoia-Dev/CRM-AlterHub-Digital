<?php

namespace App\Filament\Resources\Produtos;

use App\Filament\Resources\Produtos\Pages\ManageProdutos;
use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\ProdutoInsumo;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\ProdutoPricingCalculator;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProdutoResource extends Resource
{
    protected static ?string $model = Produto::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Produtos';

    protected static ?string $modelLabel = 'Produto';

    protected static ?string $pluralModelLabel = 'Produtos';

    public static ?string $slug = 'produtos';

    protected static string | UnitEnum | null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('ativo')->default(true),
                Hidden::make('custo_base_formacao'),
                Hidden::make('preco_sugerido'),

                Tabs::make('Cadastro de produto')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Dados gerais')
                            ->schema([
                                Section::make('Dados gerais')
                                    ->description('Ficha tecnica e comercial do produto.')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('codigo_interno')
                                            ->label('SKU / codigo interno')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(table: 'produtos', column: 'codigo_interno', ignoreRecord: true)
                                            ->placeholder('Ex.: UBT-QPCR-RESP-96'),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options(Produto::statusOptions())
                                            ->default('em_registro')
                                            ->required(),

                                        TextInput::make('nome')
                                            ->label('Nome do produto')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Select::make('categoria_produto_id')
                                            ->label('Categoria')
                                            ->relationship('categoriaProduto', 'nome')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('nome')
                                                    ->label('Nome da categoria')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->required(),

                                        TextInput::make('marca')
                                            ->label('Marca / fabricante')
                                            ->maxLength(255),

                                        TextInput::make('unidade_medida')
                                            ->label('Unidade de venda')
                                            ->required()
                                            ->maxLength(50)
                                            ->placeholder('Ex.: kit, un, cx'),

                                        TextInput::make('ncm')
                                            ->label('NCM')
                                            ->maxLength(10)
                                            ->placeholder('0000.00.00'),

                                        TextInput::make('estoque_minimo')
                                            ->label('Estoque minimo')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('0,0000'),

                                        TextInput::make('preco_tabela')
                                            ->label('Preco base')
                                            ->numeric()
                                            ->prefix('R$')
                                            ->minValue(0.01)
                                            ->required(),

                                        TextInput::make('preco_minimo')
                                            ->label('Preco minimo')
                                            ->numeric()
                                            ->prefix('R$')
                                            ->minValue(0.01)
                                            ->required(),

                                        Textarea::make('descricao')
                                            ->label('Descricao')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        Textarea::make('observacao')
                                            ->label('Observacoes internas')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Formacao de custos')
                            ->schema([
                                Section::make('Composicao por insumos')
                                    ->description('Monte a ficha tecnica do produto com snapshots de custo unitario.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('produtoInsumos')
                                            ->relationship()
                                            ->orderColumn('ordem')
                                            ->columnSpanFull()
                                            ->addActionLabel('Adicionar insumo')
                                            ->collapsible()
                                            ->live()
                                            ->schema([
                                                Select::make('insumo_id')
                                                    ->label('Insumo')
                                                    ->relationship('insumo', 'nome')
                                                    ->getOptionLabelFromRecordUsing(
                                                        fn(Insumo $record): string => $record->codigo_interno
                                                            ? "{$record->codigo_interno} - {$record->nome}"
                                                            : $record->nome
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::syncInsumoSnapshotLine($set, $get)),

                                                TextInput::make('quantidade')
                                                    ->label('Quantidade')
                                                    ->numeric()
                                                    ->minValue(0.0001)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::syncInsumoSnapshotLine($set, $get)),

                                                TextInput::make('unidade_consumo')
                                                    ->label('Unidade de consumo')
                                                    ->maxLength(50)
                                                    ->placeholder('Ex.: mL, g, un'),

                                                Hidden::make('custo_unitario_snapshot'),
                                                Hidden::make('custo_total_snapshot'),

                                                Placeholder::make('custo_unitario_snapshot_preview')
                                                    ->label('Custo unitario snapshot')
                                                    ->content(fn(Get $get): string => static::formatCurrency(
                                                        (float) ($get('custo_unitario_snapshot') ?? 0)
                                                    )),

                                                Placeholder::make('custo_total_snapshot_preview')
                                                    ->label('Custo total snapshot')
                                                    ->content(fn(Get $get): string => static::formatCurrency(
                                                        (float) ($get('custo_total_snapshot') ?? 0)
                                                    )),
                                            ])
                                            ->defaultItems(0),
                                    ]),

                                Section::make('Encargos, despesas e margem')
                                    ->description('Cadastre percentuais e custos fixos que compoem o preco sugerido.')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('produtoComponentesCusto')
                                            ->relationship()
                                            ->orderColumn('ordem')
                                            ->columnSpanFull()
                                            ->addActionLabel('Adicionar componente')
                                            ->collapsible()
                                            ->live()
                                            ->default(fn(): array => app(ProdutoPricingCalculator::class)->defaultComponentes())
                                            ->schema([
                                                TextInput::make('nome')
                                                    ->label('Nome')
                                                    ->required()
                                                    ->maxLength(255),

                                                Select::make('categoria')
                                                    ->label('Categoria')
                                                    ->options(ProdutoComponenteCusto::categoriaOptions())
                                                    ->required(),

                                                Select::make('tipo')
                                                    ->label('Tipo')
                                                    ->options(ProdutoComponenteCusto::tipoOptions())
                                                    ->required(),

                                                TextInput::make('valor')
                                                    ->label('Valor')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->required(),

                                                Toggle::make('obrigatorio')
                                                    ->label('Obrigatorio'),

                                                Toggle::make('is_margem')
                                                    ->label('E margem')
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        if ($state) {
                                                            $set('tipo', 'percentual_sobre_venda');
                                                        }
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Resumo da formacao')
                                    ->description('Os totais sao recalculados a partir da composicao atual do formulario.')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->schema([
                                        Placeholder::make('resumo_custo_total_insumos')
                                            ->label('Custo total de insumos')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['custo_total_insumos']
                                            )),

                                        Placeholder::make('resumo_custos_adicionais')
                                            ->label('Custos adicionais')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['custos_adicionais']
                                            )),

                                        Placeholder::make('resumo_percentual_total')
                                            ->label('Percentual total')
                                            ->content(fn(Get $get): string => static::formatPercent(
                                                static::buildResumo($get)['percentual_total']
                                            )),

                                        Placeholder::make('resumo_percentual_sobre_venda')
                                            ->label('Percentual sobre venda')
                                            ->content(fn(Get $get): string => static::formatPercent(
                                                static::buildResumo($get)['percentual_sobre_venda']
                                            )),

                                        Placeholder::make('resumo_margem')
                                            ->label('Margem')
                                            ->content(fn(Get $get): string => static::formatPercent(
                                                static::buildResumo($get)['margem']
                                            )),

                                        Placeholder::make('resumo_custo_base_formacao')
                                            ->label('Custo base da formacao')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['custo_base_formacao']
                                            )),

                                        Placeholder::make('resumo_preco_sugerido')
                                            ->label('Preco sugerido')
                                            ->content(function (Get $get): string {
                                                $precoSugerido = static::buildResumo($get)['preco_sugerido'];

                                                return $precoSugerido === null
                                                    ? 'Percentuais invalidos'
                                                    : static::formatCurrency($precoSugerido);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['categoriaProduto']))
            ->columns([
                TextColumn::make('codigo_interno')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('categoriaProduto.nome')
                    ->label('Categoria')
                    ->badge()
                    ->placeholder('Sem categoria'),

                TextColumn::make('unidade_medida')
                    ->label('Unidade')
                    ->sortable(),

                TextColumn::make('preco_tabela')
                    ->label('Preco base')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('preco_minimo')
                    ->label('Preco minimo')
                    ->money('BRL')
                    ->sortable()
                    ->placeholder('Nao informado'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return 'Nao definido';
                        }

                        return Produto::statusOptions()[$state] ?? $state;
                    }),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('nome')
            ->searchPlaceholder('Buscar por nome ou codigo...')
            ->filters([
                SelectFilter::make('categoria_produto_id')
                    ->label('Categoria')
                    ->relationship('categoriaProduto', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Produto::statusOptions()),
            ])
            ->recordActions([
                static::configureEditAction(EditAction::make()->label('Editar')),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProdutos::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Novo produto')
            ->modalWidth('7xl')
            ->createAnother(false)
            ->mutateFormDataUsing(fn(array $data): array => app(ProdutoPricingCalculator::class)->prepareForPersistence($data));
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->modalWidth('7xl')
            ->fillForm(fn(Produto $record): array => static::getModalFormData($record))
            ->mutateFormDataUsing(fn(array $data): array => app(ProdutoPricingCalculator::class)->prepareForPersistence($data));
    }

    public static function getModalFormData(Produto $record): array
    {
        return [
            ...$record->attributesToArray(),
            'produtoInsumos' => $record->produtoInsumos()
                ->orderBy('ordem')
                ->get()
                ->map(fn(ProdutoInsumo $item): array => [
                    'id' => $item->id,
                    'insumo_id' => $item->insumo_id,
                    'quantidade' => (float) $item->quantidade,
                    'unidade_consumo' => $item->unidade_consumo,
                    'ordem' => $item->ordem,
                    'custo_unitario_snapshot' => (float) $item->custo_unitario_snapshot,
                    'custo_total_snapshot' => (float) $item->custo_total_snapshot,
                ])
                ->all(),
            'produtoComponentesCusto' => $record->produtoComponentesCusto()
                ->orderBy('ordem')
                ->get()
                ->map(fn(ProdutoComponenteCusto $item): array => [
                    'id' => $item->id,
                    'nome' => $item->nome,
                    'categoria' => $item->categoria,
                    'tipo' => $item->tipo,
                    'valor' => (float) $item->valor,
                    'obrigatorio' => $item->obrigatorio,
                    'is_margem' => $item->is_margem,
                    'ordem' => $item->ordem,
                ])
                ->all(),
        ];
    }

    protected static function syncInsumoSnapshotLine(Set $set, Get $get): void
    {
        $line = app(ProdutoPricingCalculator::class)->refreshInsumoSnapshots([[
            'insumo_id' => $get('insumo_id'),
            'quantidade' => $get('quantidade'),
            'custo_unitario_snapshot' => $get('custo_unitario_snapshot'),
            'custo_total_snapshot' => $get('custo_total_snapshot'),
        ]])[0] ?? null;

        if (! $line) {
            return;
        }

        $set('custo_unitario_snapshot', $line['custo_unitario_snapshot']);
        $set('custo_total_snapshot', $line['custo_total_snapshot']);
    }

    protected static function buildResumo(Get $get): array
    {
        return app(ProdutoPricingCalculator::class)->summarizeState([
            'produtoInsumos' => $get('produtoInsumos') ?? [],
            'produtoComponentesCusto' => $get('produtoComponentesCusto') ?? [],
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
