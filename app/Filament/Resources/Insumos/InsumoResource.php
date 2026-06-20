<?php

namespace App\Filament\Resources\Insumos;

use App\Enum\RolesEnum;
use App\Filament\Resources\Insumos\Actions\ApplyBulkCostAction;
use App\Filament\Resources\Insumos\Pages\ManageInsumos;
use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use App\Models\Status\StatusInsumo;
use App\Services\Produtos\CatalogFormFillService;
use App\Services\Produtos\InsumoCostCalculator;
use App\Services\Produtos\InsumoCostingService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
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

class InsumoResource extends Resource
{
    protected static ?string $model = Insumo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Insumos';

    protected static ?string $modelLabel = 'Insumo';

    protected static ?string $pluralModelLabel = 'Insumos';

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.insumos.forms.modal-chip')
                    ->hidden(fn ($record): bool => blank($record?->codigo_interno))
                    ->viewData(fn ($record): array => [
                        'codigoInterno' => $record?->codigo_interno,
                    ])
                    ->columnSpanFull(),

                SchemaActions::make([
                    static::makeAutoFillAction(),
                ])
                    ->alignment(Alignment::End)
                    ->columnSpanFull(),

                static::makeIdentificacaoSection(),
                static::makeFornecimentoSection(),
                static::makeObservacoesSection(),
                static::makeHistoricoSection(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['fornecedor', 'tipoInsumo', 'statusInsumo', 'tipoUnidadeMedida', 'insumoFatoresCusto'])
                ->withSum('insumoMovimentacoes as estoque_atual', 'impacto_estoque')
                ->withCount('insumoMovimentacoes'))
            ->checkIfRecordIsSelectableUsing(fn (Insumo $record): bool => auth()->user()?->can('update', $record) ?? false)
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
                            ->description(fn (Insumo $record): ?string => $record->tipoInsumo?->nome)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('fornecedor.razao_social')
                            ->label('Fornecedor')
                            ->searchable()
                            ->placeholder('Nao informado')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('statusInsumo.nome')
                        ->label('Status')
                        ->badge()
                        ->description(fn (Insumo $record): ?string => $record->estoqueEstaBaixo() ? 'Abaixo do minimo' : null)
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
                            ->description(fn (Insumo $record): ?string => $record->tipoUnidadeMedida?->sigla ?: $record->tipoUnidadeMedida?->nome)
                            ->color(function (Insumo $record): string {
                                if (! $record->possuiHistoricoEstoque()) {
                                    return 'gray';
                                }

                                return $record->estoqueEstaBaixo() ? 'danger' : 'success';
                            })
                            ->formatStateUsing(function ($state, Insumo $record): string {
                                if (! $record->possuiHistoricoEstoque()) {
                                    return 'Sem historico';
                                }

                                return static::formatQuantity((float) $state);
                            })
                            ->extraAttributes(['class' => 'crm-list-field crm-list-stock'], merge: true),

                        TextColumn::make('estoque_minimo')
                            ->label('Minimo')
                            ->description('Minimo', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(function ($state, Insumo $record): string {
                                if ($state === null) {
                                    return '-';
                                }

                                $unit = $record->tipoUnidadeMedida?->sigla ?: $record->tipoUnidadeMedida?->nome;

                                return trim(static::formatDecimal((float) $state, 0) . ' ' . $unit);
                            })
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('origem')
                            ->label('Origem')
                            ->description('Origem', position: 'above')
                            ->badge()
                            ->formatStateUsing(function (?string $state): string {
                                if (! $state) {
                                    return 'Nao definida';
                                }

                                return Insumo::origemOptions()[$state] ?? $state;
                            })
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('moeda_origem')
                            ->label('Moeda')
                            ->description('Moeda', position: 'above')
                            ->badge()
                            ->sortable()
                            ->formatStateUsing(fn (?string $state, Insumo $record): string => $record->origem === 'nacional' ? 'BRL' : ($state ?: '-'))
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),

                Grid::make([
                    'default' => 1,
                    'md' => 3,
                ])
                    ->schema([
                        TextColumn::make('custo_moeda_origem')
                            ->label('Valor moeda origem')
                            ->description('Valor moeda origem', position: 'above')
                            ->alignEnd()
                            ->sortable()
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state): string => $state === null ? '-' : static::formatDecimal((float) $state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('taxa_cambio')
                            ->label('Taxa de cambio')
                            ->description('Taxa de cambio', position: 'above')
                            ->alignEnd()
                            ->sortable()
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state): string => $state === null ? '-' : static::formatDecimal((float) $state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('valor_convertido_brl')
                            ->label('Custo efetivo')
                            ->description('Custo efetivo', position: 'above')
                            ->formatStateUsing(fn ($state, Insumo $record): string => static::formatCurrency($record->effectiveCostAmount()))
                            ->sortable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),

                        TextColumn::make('custo_nacionalizado')
                            ->label('Custo final')
                            ->description('Custo final', position: 'above')
                            ->formatStateUsing(fn ($state, Insumo $record): string => static::formatCurrency($record->finalCostAmount()))
                            ->sortable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-finance']),
            ])
            ->defaultSort('nome')
            ->searchPlaceholder('Buscar por nome, codigo, fornecedor ou NCM...')
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
            'index' => ManageInsumos::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return static::configureModalAction($action)
            ->label('Novo insumo')
            ->createAnother(false)
            ->mutateFormDataUsing(fn (array $data): array => app(InsumoCostCalculator::class)->prepareForPersistence($data));
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return static::configureModalAction($action, withDelete: true)
            ->fillForm(fn (Insumo $record): array => static::getModalFormData($record))
            ->mutateFormDataUsing(fn (array $data): array => app(InsumoCostCalculator::class)->prepareForPersistence($data));
    }

    public static function getModalFormData(Insumo $record): array
    {
        return app(InsumoCostingService::class)->formData($record);
    }

    protected static function configureModalAction(CreateAction|EditAction $action, bool $withDelete = false): CreateAction|EditAction
    {
        return $action
            ->modalWidth('5xl')
            ->modalIcon(null)
            ->modalHeading($withDelete ? 'Editar insumo' : 'Novo insumo')
            ->modalDescription('Cadastro tecnico, origem comercial, estoque minimo e custo efetivo do insumo.')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitActionLabel('Salvar insumo')
            ->extraModalWindowAttributes([
                'class' => 'insumo-modal-window',
            ])
            ->stickyModalHeader()
            ->modalFooterActions(fn () => static::buildModalFooterActions($action, $withDelete));
    }

    protected static function buildModalFooterActions(CreateAction|EditAction $action, bool $withDelete = false): array
    {
        $footerActions = [];

        if ($withDelete && $action->getRecord()) {
            $footerActions[] = DeleteAction::make('deleteInsumoFromModal')
                ->label('Excluir insumo')
                ->record($action->getRecord())
                ->extraAttributes([
                    'class' => 'insumo-footer-delete',
                ]);
        }

        if ($cancelAction = $action->getModalCancelAction()) {
            $footerActions[] = $cancelAction->extraAttributes([
                'class' => 'insumo-footer-cancel',
            ]);
        }

        if ($submitAction = $action->getModalSubmitAction()) {
            $footerActions[] = $submitAction->extraAttributes([
                'class' => 'insumo-footer-submit',
            ]);
        }

        return $footerActions;
    }

    protected static function makeAutoFillAction(): Action
    {
        return Action::make('fillInsumoForm')
            ->label('Fill')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->authorize(fn (): bool => static::canUseAutoFill())
            ->requiresConfirmation()
            ->modalHeading('Aplicar fill do formulario?')
            ->modalDescription('Os valores atuais serao substituidos por um preenchimento automatico de exemplo.')
            ->action(function (Set $set): void {
                try {
                    static::fillFormState($set, app(CatalogFormFillService::class)->insumo());

                    Notification::make()
                        ->title('Formulario preenchido')
                        ->body('Os campos do insumo receberam um exemplo completo para agilizar o cadastro.')
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

    protected static function makeIdentificacaoSection(): Section
    {
        return Section::make('Identificacao')
            ->description('Cadastro principal, classificacao e limite minimo de estoque.')
            ->icon(Heroicon::OutlinedBeaker)
            ->columns(12)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'insumo-form-section'])
            ->schema([
                TextInput::make('codigo_interno')
                    ->label('Codigo interno')
                    ->placeholder('Ex.: INS-UBT-0001 (gerado ao salvar)')
                    ->helperText('Padrao sugerido: INS-UBT-####')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                TextInput::make('nome')
                    ->label('Nome do insumo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Denominacao tecnica ou comercial')
                    ->columnSpanFull(),

                Radio::make('origem')
                    ->label('Origem')
                    ->options(Insumo::origemOptions())
                    ->descriptions([
                        'nacional' => 'Compra e custo em BRL.',
                        'importado' => 'Exige moeda de origem, cambio manual e fatores adicionais.',
                    ])
                    ->default('nacional')
                    ->required()
                    ->inline()
                    ->live()
                    ->columnSpanFull(),

                Textarea::make('descricao')
                    ->label('Descricao / especificacoes')
                    ->rows(4)
                    ->maxLength(1000)
                    ->placeholder('Pureza, grade, embalagem, validade tipica...')
                    ->columnSpanFull(),

                Select::make('tipo_insumo_id')
                    ->label('Tipo')
                    ->relationship('tipoInsumo', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (TipoInsumo $record): string => $record->nome)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(6),

                Select::make('tipo_unidade_medida_id')
                    ->label('Unidade de Medida')
                    ->relationship('tipoUnidadeMedida', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (TipoUnidadeMedida $record): string => static::formatUnidadeLabel($record))
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ex.: Mililitro'),

                        TextInput::make('sigla')
                            ->label('Sigla')
                            ->maxLength(20)
                            ->placeholder('Ex.: mL'),
                    ])
                    ->required()
                    ->columnSpan(6),

                Select::make('tipo_armazenamento_id')
                    ->label('Armazenamento')
                    ->relationship('tipoArmazenamento', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (TipoArmazenamento $record): string => $record->nome)
                    ->searchable()
                    ->preload()
                    ->columnSpan(6),

                Select::make('status_insumo_id')
                    ->label('Status')
                    ->relationship('statusInsumo', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (StatusInsumo $record): string => $record->nome)
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => static::resolveDefaultStatusId())
                    ->required()
                    ->columnSpan(6),

                TextInput::make('ncm')
                    ->label('NCM')
                    ->maxLength(10)
                    ->placeholder('8 digitos')
                    ->columnSpan(6),

                TextInput::make('estoque_minimo')
                    ->label('Estoque minimo (alerta)')
                    ->numeric()
                    ->rule('decimal:0,2')
                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                    ->minValue(0)
                    ->placeholder('0,00')
                    ->columnSpan(6),
            ]);
    }

    protected static function makeFornecimentoSection(): Section
    {
        return Section::make('Fornecimento e custo')
            ->description('Fornecedor preferencial, origem e composicao do custo efetivo.')
            ->icon(Heroicon::OutlinedChartBar)
            ->columns(12)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'insumo-form-section'])
            ->schema([
                Section::make('Dados de origem')
                    ->description('Base de custo conforme a origem do insumo.')
                    ->columns(12)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'insumo-subsection'])
                    ->schema([
                        Select::make('fornecedor_id')
                            ->label('Fornecedor preferencial')
                            ->relationship('fornecedor', 'razao_social')
                            ->getOptionLabelFromRecordUsing(fn (Fornecedor $record): string => static::formatFornecedorLabel($record))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('custo_referencia')
                            ->label('Custo unitario')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->visible(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->placeholder('0,00')
                            ->prefix('R$')
                            ->live()
                            ->columnSpan(8),

                        TextInput::make('moeda_brl_preview')
                            ->label('Moeda')
                            ->default('BRL')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->columnSpan(4),

                        TextInput::make('custo_moeda_origem')
                            ->label('Custo unitario na moeda de origem')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->placeholder('0,00')
                            ->live()
                            ->columnSpan(4),

                        TextInput::make('taxa_cambio')
                            ->label('Taxa de cambio')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0.000001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->placeholder('0,00')
                            ->helperText('Sempre manual. Nao ha valor fixo de negocio nem integracao automatica nesta fase.')
                            ->live()
                            ->columnSpan(4),

                        Select::make('moeda_origem')
                            ->label('Moeda de origem')
                            ->options(Insumo::moedaOptions())
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->live()
                            ->columnSpan(4),

                        Hidden::make('valor_convertido_brl'),
                    ]),

                Section::make('Fatores de custo')
                    ->description('Aplicados apenas quando o insumo for importado.')
                    ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                    ->columns(12)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'insumo-subsection'])
                    ->schema([
                        Hidden::make('show_existing_factor_picker')
                            ->default(false)
                            ->dehydrated(false)
                            ->live(),

                        SchemaActions::make([
                            static::makeShowExistingFactorPickerAction(),
                        ])
                            ->alignment(Alignment::Start)
                            ->columnSpanFull(),

                        Select::make('existing_factor_template')
                            ->label('Adicionar fator existente')
                            ->placeholder('Selecione uma opcao')
                            ->options(fn (Get $get): array => static::getAvailableExistingFactorTemplateOptions($get))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => $get('show_existing_factor_picker') && static::hasAvailableExistingFactorTemplates($get))
                            ->extraAttributes([
                                'class' => 'insumo-existing-factor-select',
                            ])
                            ->extraAlpineAttributes([
                                'x-on:change' => 'window.dispatchEvent(new CustomEvent("insumo-existing-factor-selected"))',
                            ])
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $template = static::decodeFactorTemplate($state);

                                if (! $template) {
                                    return;
                                }

                                $fatores = collect($get('insumoFatoresCusto') ?? [])
                                    ->filter(fn (mixed $item): bool => is_array($item))
                                    ->values()
                                    ->all();

                                $fatores[] = [
                                    'nome' => $template['nome'],
                                    'tipo' => $template['tipo'],
                                    'valor' => $template['valor'],
                                    'ordem' => count($fatores) + 1,
                                ];

                                $set('insumoFatoresCusto', $fatores);
                                $set('existing_factor_template', null);
                                $set('show_existing_factor_picker', false);
                            })
                            ->columnSpanFull(),

                        Repeater::make('insumoFatoresCusto')
                            ->label('')
                            ->relationship()
                            ->orderColumn('ordem')
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Nome do fator')->markAsRequired(),
                                TableColumn::make('Tipo')->markAsRequired(),
                                TableColumn::make('Valor')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('nome')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ex.: Frete internacional'),

                                Select::make('tipo')
                                    ->hiddenLabel()
                                    ->options(InsumoFatorCusto::tipoOptions())
                                    ->required()
                                    ->live(),

                                TextInput::make('valor')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->rule('decimal:0,2')
                                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                    ->minValue(0)
                                    ->required()
                                    ->placeholder('0,00')
                                    ->live(),
                            ])
                            ->addActionLabel('Criar novo fator')
                            ->addActionAlignment(Alignment::Start)
                            ->defaultItems(0)
                            ->reorderableWithDragAndDrop(false)
                            ->reorderableWithButtons(),
                    ]),

                Section::make('Resumo do calculo')
                    ->description('Atualizado automaticamente conforme a origem e os fatores informados.')
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'insumo-subsection'])
                    ->schema([
                        View::make('filament.resources.insumos.forms.cost-summary')
                            ->columnSpanFull()
                            ->viewData(fn (Get $get): array => [
                                'origem' => $get('origem'),
                                'summary' => static::buildCostSummary($get),
                                'factorLines' => static::buildFactorBreakdown($get('insumoFatoresCusto') ?? []),
                            ]),
                    ]),
            ]);
    }

    protected static function makeObservacoesSection(): Section
    {
        return Section::make('Observacoes')
            ->description('Notas internas e orientacoes operacionais do insumo.')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'insumo-form-section'])
            ->schema([
                Textarea::make('observacao')
                    ->label('Notas internas')
                    ->rows(4)
                    ->maxLength(2000)
                    ->placeholder('Informacoes para compras, qualidade ou producao.')
                    ->columnSpanFull(),
            ]);
    }

    protected static function makeHistoricoSection(): Section
    {
        return Section::make('Historico')
            ->description('Saldo atual, alerta de estoque e ultimas movimentacoes do insumo.')
            ->icon(Heroicon::OutlinedClock)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'insumo-form-section'])
            ->schema([
                View::make('filament.resources.insumos.forms.history')
                    ->columnSpanFull()
                    ->viewData(fn ($record): array => [
                        'record' => $record,
                    ]),
            ]);
    }

    protected static function makeShowExistingFactorPickerAction(): Action
    {
        return Action::make('showExistingCostFactorPicker')
            ->label('Adicionar fator existente')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('gray')
            ->visible(fn (Get $get): bool => (! $get('show_existing_factor_picker')) && static::hasAvailableExistingFactorTemplates($get))
            ->extraAttributes([
                'class' => 'insumo-existing-factor-picker-trigger',
            ])
            ->dispatch('insumo-existing-factor-picker-opened')
            ->action(function (Set $set): void {
                $set('show_existing_factor_picker', true);
            });
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

    protected static function buildFactorBreakdown(array $fatores): array
    {
        return collect($fatores)
            ->filter(fn (mixed $fator): bool => is_array($fator) && filled($fator['nome'] ?? null))
            ->map(function (array $fator): array {
                $tipo = $fator['tipo'] ?? null;
                $valor = (float) ($fator['valor'] ?? 0);

                return [
                    'nome' => $fator['nome'],
                    'tipo' => InsumoFatorCusto::tipoOptions()[$tipo] ?? 'Nao definido',
                    'valor' => static::formatCostFactorValue($tipo, $valor),
                ];
            })
            ->values()
            ->all();
    }

    protected static function hasAvailableExistingFactorTemplates(Get $get): bool
    {
        return static::getAvailableExistingFactorTemplateOptions($get) !== [];
    }

    protected static function getAvailableExistingFactorTemplateOptions(Get $get): array
    {
        $selectedSignatures = collect($get('insumoFatoresCusto') ?? [])
            ->filter(fn (mixed $fator): bool => is_array($fator))
            ->map(fn (array $fator): string => static::factorTemplateSignature($fator))
            ->filter()
            ->values()
            ->all();

        return collect(static::getExistingFactorTemplates())
            ->reject(fn (array $template): bool => in_array(static::factorTemplateSignature($template), $selectedSignatures, true))
            ->mapWithKeys(fn (array $template): array => [
                static::encodeFactorTemplate($template) => static::formatFactorTemplateLabel($template),
            ])
            ->all();
    }

    protected static function getExistingFactorTemplates(): array
    {
        return InsumoFatorCusto::query()
            ->orderBy('nome')
            ->orderBy('tipo')
            ->get(['nome', 'tipo', 'valor'])
            ->map(fn (InsumoFatorCusto $fator): array => [
                'nome' => $fator->nome,
                'tipo' => $fator->tipo,
                'valor' => (float) $fator->valor,
            ])
            ->unique(fn (array $template): string => static::factorTemplateSignature($template))
            ->values()
            ->all();
    }

    protected static function encodeFactorTemplate(array $template): string
    {
        return base64_encode(json_encode([
            'nome' => (string) ($template['nome'] ?? ''),
            'tipo' => $template['tipo'] ?? null,
            'valor' => round((float) ($template['valor'] ?? 0), 4),
        ]) ?: '');
    }

    protected static function decodeFactorTemplate(?string $payload): ?array
    {
        if (blank($payload)) {
            return null;
        }

        $decoded = json_decode(base64_decode($payload, true) ?: '', true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'nome' => (string) ($decoded['nome'] ?? ''),
            'tipo' => $decoded['tipo'] ?? null,
            'valor' => round((float) ($decoded['valor'] ?? 0), 4),
        ];
    }

    protected static function factorTemplateSignature(array $template): string
    {
        return implode('|', [
            trim((string) ($template['nome'] ?? '')),
            (string) ($template['tipo'] ?? ''),
            number_format((float) ($template['valor'] ?? 0), 4, '.', ''),
        ]);
    }

    protected static function formatFactorTemplateLabel(array|InsumoFatorCusto $template): string
    {
        $tipo = static::formatCostFactorValue(
            $template['tipo'] ?? null,
            (float) ($template['valor'] ?? 0),
        );

        return "{$template['nome']} ({$tipo})";
    }

    protected static function formatCostFactorValue(?string $tipo, float $valor): string
    {
        if ($tipo !== 'percentual') {
            return static::formatCurrency($valor);
        }

        return $valor > 0 && $valor < 1
            ? static::formatDecimal($valor)
            : static::formatPercent($valor);
    }

    protected static function formatFornecedorLabel(Fornecedor $record): string
    {
        if (filled($record->codigo_interno)) {
            return "{$record->codigo_interno} - {$record->razao_social}";
        }

        if (filled($record->nome_fantasia)) {
            return "{$record->razao_social} ({$record->nome_fantasia})";
        }

        return $record->razao_social;
    }

    protected static function formatUnidadeLabel(TipoUnidadeMedida $record): string
    {
        return $record->sigla
            ? "{$record->nome} ({$record->sigla})"
            : $record->nome;
    }

    protected static function resolveDefaultStatusId(): ?int
    {
        return StatusInsumo::query()
            ->where('nome', 'like', '%registro%')
            ->value('id')
            ?? StatusInsumo::query()->orderBy('id')->value('id');
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

    protected static function formatDecimal(float|int|null $value, int $decimals = 2): string
    {
        return NumericFormat::decimal($value, $decimals);
    }
}
