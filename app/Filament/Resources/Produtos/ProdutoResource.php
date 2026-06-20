<?php

namespace App\Filament\Resources\Produtos;

use App\Enum\RolesEnum;
use App\Filament\Resources\Produtos\Actions\ApplyBulkCostAction;
use App\Filament\Resources\Produtos\Pages\ManageProdutos;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\CatalogFormFillService;
use App\Services\Produtos\ProdutoCostingService;
use App\Services\Produtos\ProdutoPricingCalculator;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
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

                SchemaActions::make([
                    static::makeAutoFillAction(),
                ])
                    ->alignment(Alignment::End)
                    ->columnSpanFull(),

                Tabs::make('Cadastro de produto')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Dados gerais')
                            ->schema([
                                Section::make('Dados gerais')
                                    ->description('Ficha tecnica, status comercial e controle minimo de estoque.')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('codigo_interno')
                                            ->label('SKU / codigo interno')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Gerado automaticamente ao salvar')
                                            ->helperText('Esse codigo e criado automaticamente no cadastro do produto.'),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options(Produto::statusOptions())
                                            ->default('ativo')
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

                                        Select::make('unidade_medida')
                                            ->label('Unidade de venda')
                                            ->options(fn (): array => static::unidadeMedidaOptions())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->placeholder('Selecione uma unidade'),

                                        TextInput::make('ncm')
                                            ->label('NCM')
                                            ->maxLength(10)
                                            ->placeholder('0000.00.00'),

                                        TextInput::make('estoque_minimo')
                                            ->label('Estoque minimo')
                                            ->numeric()
                                            ->rule('decimal:0,2')
                                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                            ->minValue(0)
                                            ->placeholder('0,00')
                                            ->helperText('Usado como referencia de reposicao na listagem e nas movimentacoes.'),

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
                                Section::make('Como este produto sera formado?')
                                    ->description('Escolha se o custo vem de uma composicao de insumos ou de um produto pronto comprado de um fornecedor.')
                                    ->icon(Heroicon::OutlinedCog6Tooth)
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        Toggle::make('produto_unico_sem_insumo')
                                            ->label('Industrializados e Revenda')
                                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Use quando o produto ja chega pronto e nao precisa montar uma ficha tecnica de insumos.')
                                            ->inline(false)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, bool $state): void {
                                                if ($state) {
                                                    $set('produtoInsumos', []);
                                                }
                                            }),
                                    ]),

                                Section::make('Composicao por insumos')
                                    ->description('Use quando o produto e montado pela soma de um ou mais insumos.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => ! (bool) $get('produto_unico_sem_insumo'))
                                    ->schema([
                                        Repeater::make('produtoInsumos')
                                            ->relationship()
                                            ->orderColumn('ordem')
                                            ->columnSpanFull()
                                            ->addActionLabel('Adicionar insumo')
                                            ->table([
                                                TableColumn::make('Insumo')->markAsRequired(),
                                                TableColumn::make('Qtd.')->markAsRequired(),
                                                TableColumn::make('Unidade'),
                                                TableColumn::make('Custo unit.'),
                                                TableColumn::make('Custo total'),
                                            ])
                                            ->live()
                                            ->schema([
                                                Select::make('insumo_id')
                                                    ->hiddenLabel()
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
                                                    ->hiddenLabel()
                                                    ->numeric()
                                                    ->rule('decimal:0,2')
                                                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                                    ->minValue(0.0001)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn(Set $set, Get $get) => static::syncInsumoSnapshotLine($set, $get)),

                                                TextInput::make('unidade_consumo')
                                                    ->hiddenLabel()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->placeholder('Automatico pelo insumo'),

                                                Hidden::make('custo_unitario_snapshot'),
                                                Hidden::make('custo_total_snapshot'),

                                                Placeholder::make('custo_unitario_snapshot_preview')
                                                    ->hiddenLabel()
                                                    ->content(fn(Get $get): string => static::formatCurrency(
                                                        (float) ($get('custo_unitario_snapshot') ?? 0)
                                                    )),

                                                Placeholder::make('custo_total_snapshot_preview')
                                                    ->hiddenLabel()
                                                    ->content(fn(Get $get): string => static::formatCurrency(
                                                        (float) ($get('custo_total_snapshot') ?? 0)
                                                    )),
                                            ])
                                            ->defaultItems(0)
                                            ->reorderableWithDragAndDrop(false)
                                            ->reorderableWithButtons(),
                                    ]),

                                Section::make('Preco base do produto')
                                    ->description('Valor inicial usado para aplicar os fatores e formar o preco de venda minimo.')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('custo_produto_unico')
                                            ->label('Preco base do produto')
                                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Valor pago ou custo base do produto antes dos fatores e do lucro.')
                                            ->numeric()
                                            ->rule('decimal:0,2')
                                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                            ->prefix('R$')
                                            ->minValue(0.01)
                                            ->placeholder(fn (Get $get): string => NumericFormat::input(
                                                static::buildResumo($get)['custo_total_insumos'] ?? 0
                                            ) ?? '0,00')
                                            ->required(fn (Get $get): bool => (bool) $get('produto_unico_sem_insumo'))
                                            ->live(),

                                        Placeholder::make('sugestao_preco_base_insumos')
                                            ->label('Sugestao pelos insumos')
                                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Soma dos custos totais dos insumos. Se o preco base ficar vazio, esta sugestao sera usada no calculo.')
                                            ->content(fn (Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['custo_total_insumos']
                                            ))
                                            ->visible(fn (Get $get): bool => ! (bool) $get('produto_unico_sem_insumo')),
                                    ]),

                                Section::make('Fatores do produto')
                                    ->description('Mesma logica dos fatores dos insumos: valores fixos e percentuais aplicados sobre o preco do produto.')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('lucro_percentual')
                                            ->label('Percentual de lucro')
                                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Percentual adicionado ao preco de venda minimo para formar o preco de venda final.')
                                            ->numeric()
                                            ->rule('decimal:0,2')
                                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->placeholder('0,00')
                                            ->live(),

                                        static::makeProductFactorsRepeater(),
                                    ]),

                                Section::make('Resumo da formacao')
                                    ->description('O valor final do produto e o limite minimo de venda sao recalculados automaticamente.')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->schema([
                                        Placeholder::make('resumo_preco_produto')
                                            ->label('Preco base do produto')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['preco_produto']
                                            )),

                                        Placeholder::make('resumo_fatores_fixos')
                                            ->label('Fatores fixos')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['soma_fixos_brl']
                                            )),

                                        Placeholder::make('resumo_fatores_percentuais')
                                            ->label('Fatores percentuais')
                                            ->content(fn(Get $get): string => static::formatPercent(
                                                static::buildResumo($get)['soma_percentuais']
                                            )),

                                        Placeholder::make('resumo_valor_final_produto')
                                            ->label('Preco de venda minimo')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['preco_minimo']
                                            )),

                                        Placeholder::make('resumo_percentual_lucro')
                                            ->label('Percentual de lucro')
                                            ->content(fn(Get $get): string => static::formatPercent(
                                                static::buildResumo($get)['percentual_lucro']
                                            )),

                                        Placeholder::make('resumo_preco_venda_final')
                                            ->label('Preco de venda final')
                                            ->content(fn(Get $get): string => static::formatCurrency(
                                                static::buildResumo($get)['preco_venda_final']
                                            )),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->with(['categoriaProduto'])
                ->withSum('produtoMovimentacoes as estoque_atual', 'impacto_estoque')
                ->withCount('produtoMovimentacoes'))
            ->checkIfRecordIsSelectableUsing(fn (Produto $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->columns([
                Split::make([
                    TextColumn::make('codigo_interno')
                        ->label('Codigo')
                        ->description('Codigo', position: 'above')
                        ->searchable()
                        ->sortable()
                        ->fontFamily('mono')
                        ->badge()
                        ->color('gray')
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),

                    Stack::make([
                        TextColumn::make('nome')
                            ->label('Nome')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold')
                            ->description(fn(Produto $record): ?string => $record->marca)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('categoriaProduto.nome')
                            ->label('Categoria')
                            ->badge()
                            ->placeholder('Sem categoria')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(function (?string $state): string {
                            if ($state === 'em_registro') {
                                $state = 'ativo';
                            }

                            if (! $state) {
                                return 'Nao definido';
                            }

                            return Produto::statusOptions()[$state] ?? $state;
                        })
                        ->description(fn (Produto $record): ?string => $record->estoqueEstaBaixo() ? 'Abaixo do minimo' : null)
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 4,
                ])
                    ->schema([
                        TextColumn::make('estoque_atual')
                            ->label('Estoque atual')
                            ->badge()
                            ->sortable()
                            ->description(fn(Produto $record): string => 'Minimo: ' . ($record->estoque_minimo === null ? '-' : static::formatQuantity($record->estoque_minimo)))
                            ->color(function (Produto $record): string {
                                if (! $record->possuiHistoricoEstoque()) {
                                    return 'gray';
                                }

                                return $record->estoqueEstaBaixo() ? 'danger' : 'success';
                            })
                            ->formatStateUsing(function ($state, Produto $record): string {
                                if (! $record->possuiHistoricoEstoque()) {
                                    return 'Sem historico';
                                }

                                return static::formatQuantity((float) $state);
                            })
                            ->extraAttributes(['class' => 'crm-list-field crm-list-stock'], merge: true),

                        TextColumn::make('unidade_medida')
                            ->label('Unidade')
                            ->description('Unidade', position: 'above')
                            ->sortable()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('preco_tabela')
                            ->label('Venda final')
                            ->description('Venda final', position: 'above')
                            ->money('BRL')
                            ->sortable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),

                        TextColumn::make('preco_minimo')
                            ->label('Venda minima')
                            ->description('Venda minima', position: 'above')
                            ->money('BRL')
                            ->sortable()
                            ->placeholder('Nao informado')
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->description('Atualizado em', position: 'above')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable()
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
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
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--catalog')
            ->recordActions([
                static::configureEditAction(EditAction::make()->label('Editar')),
                DeleteAction::make()->label('Excluir'),
            ], position: RecordActionsPosition::AfterContent)
            ->toolbarActions([
                BulkActionGroup::make([
                    ApplyBulkCostAction::make(),
                ]),
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
        return static::configureModalAction($action)
            ->label('Novo produto')
            ->createAnother(false)
            ->mutateFormDataUsing(fn(array $data): array => static::prepareProductFormData($data))
            ->after(function (Produto $record, array $data): void {
                static::syncPreparedProductRelations($record, $data);
            });
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return static::configureModalAction($action, withDelete: true)
            ->fillForm(fn(Produto $record): array => static::getModalFormData($record))
            ->mutateFormDataUsing(fn(array $data): array => static::prepareProductFormData($data))
            ->after(function (Produto $record, array $data): void {
                static::syncPreparedProductRelations($record, $data);
            });
    }

    public static function getModalFormData(Produto $record): array
    {
        return app(ProdutoCostingService::class)->formData($record);
    }

    protected static function configureModalAction(CreateAction|EditAction $action, bool $withDelete = false): CreateAction|EditAction
    {
        return $action
            ->modalWidth('6xl')
            ->modalIcon(null)
            ->modalHeading($withDelete ? 'Editar produto' : 'Novo produto')
            ->modalDescription('Ficha tecnica, estoque e formacao de custos no mesmo fluxo.')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitActionLabel('Salvar produto')
            ->extraModalWindowAttributes([
                'class' => 'produto-modal-window',
            ])
            ->stickyModalHeader();
    }

    protected static function makeAutoFillAction(): Action
    {
        return Action::make('fillProdutoForm')
            ->label('Fill')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->authorize(fn (): bool => static::canUseAutoFill())
            ->requiresConfirmation()
            ->modalHeading('Aplicar fill do formulario?')
            ->modalDescription('Os valores atuais serao substituidos por um preenchimento automatico de exemplo.')
            ->action(function (Set $set): void {
                try {
                    static::fillFormState($set, app(CatalogFormFillService::class)->produto());

                    Notification::make()
                        ->title('Formulario preenchido')
                        ->body('Os campos do produto receberam um exemplo completo para agilizar o cadastro.')
                        ->success()
                        ->send();
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('Fill indisponivel')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function canUseAutoFill(): bool
    {
        return auth()->user()?->hasRole(RolesEnum::SuperAdmin->value) ?? false;
    }

    protected static function fillFormState(Set $set, array $data): void
    {
        foreach ($data as $key => $value) {
            $set($key, $value);
        }
    }

    protected static function syncInsumoSnapshotLine(Set $set, Get $get): void
    {
        $insumoId = $get('insumo_id');

        if (blank($insumoId)) {
            $set('unidade_consumo', null);
            $set('custo_unitario_snapshot', 0);
            $set('custo_total_snapshot', 0);

            return;
        }

        $insumo = Insumo::query()
            ->with('tipoUnidadeMedida')
            ->find($insumoId);

        if ($insumo) {
            $set('unidade_consumo', static::resolveInsumoUnit($insumo));
        }

        $line = app(ProdutoPricingCalculator::class)->refreshInsumoSnapshots([[
            'insumo_id' => $insumoId,
            'quantidade' => $get('quantidade'),
            'unidade_consumo' => $get('unidade_consumo'),
            'custo_unitario_snapshot' => $get('custo_unitario_snapshot'),
            'custo_total_snapshot' => $get('custo_total_snapshot'),
        ]])[0] ?? null;

        if (! $line) {
            return;
        }

        $set('custo_unitario_snapshot', $line['custo_unitario_snapshot']);
        $set('custo_total_snapshot', $line['custo_total_snapshot']);
    }

    protected static function makeProductFactorsRepeater(): Repeater
    {
        return Repeater::make('produtoComponentesCusto')
            ->label('')
            ->relationship()
            ->orderColumn('ordem')
            ->columnSpanFull()
            ->addActionLabel('Criar novo fator')
            ->table([
                TableColumn::make('Nome do fator')->markAsRequired(),
                TableColumn::make('Tipo')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
            ])
            ->live()
            ->schema([
                TextInput::make('nome')
                    ->hiddenLabel()
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex.: Frete, embalagem, taxa operacional')
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Nome usado para identificar este fator no calculo do produto.'),

                Hidden::make('categoria')
                    ->default('fator'),

                Select::make('tipo')
                    ->hiddenLabel()
                    ->options(ProdutoComponenteCusto::tipoOptions())
                    ->required()
                    ->live()
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Percentual segue a mesma logica dos insumos; valor fixo soma diretamente ao preco do produto.'),

                TextInput::make('valor')
                    ->hiddenLabel()
                    ->numeric()
                    ->rule('decimal:0,2')
                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                    ->minValue(0)
                    ->required()
                    ->placeholder('0,00')
                    ->live()
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Valor do fator. Percentuais menores que 1 funcionam como divisor, iguais ou maiores que 1 como adicional percentual.'),

                Hidden::make('obrigatorio')
                    ->default(false),
            ])
            ->defaultItems(0)
            ->reorderableWithDragAndDrop(false)
            ->reorderableWithButtons();
    }

    protected static function buildResumo(Get $get): array
    {
        return app(ProdutoPricingCalculator::class)->summarizeState([
            'produtoInsumos' => (bool) $get('produto_unico_sem_insumo') ? [] : ($get('produtoInsumos') ?? []),
            'produtoComponentesCusto' => static::componentesFromGuidedFields([
                'produto_unico_sem_insumo' => $get('produto_unico_sem_insumo'),
                'custo_produto_unico' => $get('custo_produto_unico'),
                'lucro_percentual' => $get('lucro_percentual'),
                'produtoComponentesCusto' => $get('produtoComponentesCusto') ?? [],
            ]),
        ]);
    }

    protected static function prepareProductFormData(array $data): array
    {
        if (filter_var($data['produto_unico_sem_insumo'] ?? false, FILTER_VALIDATE_BOOL)) {
            $data['produtoInsumos'] = [];
        }

        $data['produtoComponentesCusto'] = static::componentesFromGuidedFields($data);

        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence($data);

        unset(
            $prepared['produto_unico_sem_insumo'],
            $prepared['custo_produto_unico'],
            $prepared['lucro_percentual'],
        );

        return $prepared;
    }

    protected static function componentesFromGuidedFields(array $data): array
    {
        $componentes = collect([
            ...($data['produtoComponentesCusto'] ?? []),
        ])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->reject(fn (array $item): bool => static::isGuidedComponent($item))
            ->map(function (array $item): array {
                $item['categoria'] = 'fator';
                $item['obrigatorio'] = false;

                return $item;
            })
            ->values();

        $precoBaseProduto = static::parseNumber($data['custo_produto_unico'] ?? null);

        if (filter_var($data['produto_unico_sem_insumo'] ?? false, FILTER_VALIDATE_BOOL) || $precoBaseProduto > 0) {
            $componentes->prepend(static::makeGuidedComponent(
                ProdutoPricingCalculator::SINGLE_PRODUCT_COST_COMPONENT,
                'custo_produto',
                'valor_fixo_brl',
                $precoBaseProduto,
                true,
            ));
        }

        $lucroPercentual = static::parseNumber($data['lucro_percentual'] ?? 0);

        if ($lucroPercentual > 0) {
            $componentes->push(static::makeGuidedComponent(
                ProdutoPricingCalculator::PROFIT_COMPONENT,
                'lucro',
                'percentual',
                $lucroPercentual,
                false,
            ));
        }

        return $componentes
            ->values()
            ->map(function (array $item, int $index): array {
                $item['ordem'] = $index;

                return $item;
            })
            ->all();
    }

    protected static function syncPreparedProductRelations(Produto $record, array $data): void
    {
        $prepared = array_key_exists('produtoComponentesCusto', $data)
            ? $data
            : static::prepareProductFormData($data);

        app(ProdutoCostingService::class)->syncPreparedRelations($record, $prepared, syncInsumos: false);
    }

    protected static function isGuidedComponent(array $item): bool
    {
        return in_array($item['nome'] ?? '', [
            ProdutoPricingCalculator::SINGLE_PRODUCT_COST_COMPONENT,
            ProdutoPricingCalculator::PROFIT_COMPONENT,
        ], true);
    }

    protected static function makeGuidedComponent(
        string $nome,
        string $categoria,
        string $tipo,
        float $valor,
        bool $obrigatorio,
    ): array {
        return [
            'nome' => $nome,
            'categoria' => $categoria,
            'tipo' => $tipo,
            'valor' => $valor,
            'obrigatorio' => $obrigatorio,
        ];
    }

    protected static function parseNumber(mixed $value): float
    {
        return NumericFormat::parse($value, 4) ?? 0.0;
    }

    protected static function formatCurrency(float|int|null $value): string
    {
        return NumericFormat::money($value);
    }

    protected static function formatPercent(float|int|null $value): string
    {
        return NumericFormat::percent($value);
    }

    protected static function formatQuantity(float|int|null $value): string
    {
        return NumericFormat::decimal($value);
    }

    protected static function unidadeMedidaOptions(): array
    {
        return TipoUnidadeMedida::query()
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (TipoUnidadeMedida $record): array => [
                (string) ($record->sigla ?: $record->nome) => static::formatUnidadeLabel($record),
            ])
            ->all();
    }

    protected static function formatUnidadeLabel(TipoUnidadeMedida $record): string
    {
        return $record->sigla
            ? "{$record->sigla} - {$record->nome}"
            : $record->nome;
    }

    protected static function resolveInsumoUnit(Insumo $insumo): ?string
    {
        return $insumo->tipoUnidadeMedida?->sigla
            ?: $insumo->tipoUnidadeMedida?->nome;
    }
}
