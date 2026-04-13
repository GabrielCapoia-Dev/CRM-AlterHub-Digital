<?php

namespace App\Filament\Resources\Insumos;

use App\Filament\Resources\Insumos\Pages\ManageInsumos;
use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use App\Models\Status\StatusInsumo;
use App\Services\Produtos\InsumoCostCalculator;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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

                static::makeIdentificacaoSection(),
                static::makeFornecimentoSection(),
                static::makeObservacoesSection(),
                static::makeHistoricoSection(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fornecedor', 'tipoInsumo', 'statusInsumo', 'tipoUnidadeMedida']))
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

                TextColumn::make('fornecedor.razao_social')
                    ->label('Fornecedor')
                    ->searchable()
                    ->placeholder('Nao informado'),

                TextColumn::make('origem')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        if (! $state) {
                            return 'Nao definida';
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
                            return '-';
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
            ->searchPlaceholder('Buscar por nome, codigo ou NCM...')
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

    protected static function configureModalAction(CreateAction|EditAction $action, bool $withDelete = false): CreateAction|EditAction
    {
        return $action
            ->modalWidth('4xl')
            ->modalIcon(null)
            ->modalHeading($withDelete ? 'Editar insumo' : 'Novo insumo')
            ->modalDescription('Itens para producao e laboratorio (compras / estoque). Campos com * sao obrigatorios nesta demonstracao.')
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

    protected static function makeIdentificacaoSection(): Section
    {
        return Section::make('Identificacao')
            ->description('Dados principais de cadastro e classificacao do insumo.')
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
                    ->label('Unidade de compra')
                    ->relationship('tipoUnidadeMedida', 'nome')
                    ->getOptionLabelFromRecordUsing(fn (TipoUnidadeMedida $record): string => static::formatUnidadeLabel($record))
                    ->searchable()
                    ->preload()
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
                    ->minValue(0)
                    ->placeholder('0,0000')
                    ->columnSpan(6),
            ]);
    }

    protected static function makeFornecimentoSection(): Section
    {
        return Section::make('Fornecimento e custo')
            ->description('Configure o fornecedor, a origem do insumo e a formacao do custo efetivo.')
            ->icon(Heroicon::OutlinedChartBar)
            ->columns(12)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'insumo-form-section'])
            ->schema([
                Section::make('Dados de origem')
                    ->description('Defina o fornecedor preferencial e a base de custo conforme a origem do insumo.')
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
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->visible(fn (Get $get): bool => $get('origem') === 'nacional')
                            ->placeholder('0,0000')
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
                            ->minValue(0.0001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->placeholder('0,0000')
                            ->live()
                            ->columnSpan(4),

                        TextInput::make('taxa_cambio')
                            ->label('Taxa de cambio')
                            ->numeric()
                            ->minValue(0.000001)
                            ->required(fn (Get $get): bool => $get('origem') === 'importado')
                            ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                            ->placeholder('0,000000')
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
                    ->description('Os fatores adicionados impactam o custo final do produto.')
                    ->visible(fn (Get $get): bool => $get('origem') === 'importado')
                    ->columns(12)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'insumo-subsection'])
                    ->schema([
                        SchemaActions::make([
                            static::makeAddExistingFactorAction(),
                        ])
                            ->alignment(Alignment::Start)
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
                                    ->minValue(0)
                                    ->required()
                                    ->placeholder('0,0000')
                                    ->live(),
                            ])
                            ->addActionLabel('Criar novo fator')
                            ->addActionAlignment(Alignment::Start)
                            ->defaultItems(0)
                            ->reorderableWithDragAndDrop(false)
                            ->reorderableWithButtons(),
                    ]),

                Section::make('Resumo do calculo')
                    ->description('Calculado automaticamente com base nos dados de origem e fatores.')
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
            ->description('Notas internas, requisitos regulatorios ou apontamentos operacionais.')
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
            ->description('Resumo do ultimo salvamento e disponibilidade do historico operacional.')
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

    protected static function makeAddExistingFactorAction(): Action
    {
        return Action::make('addExistingCostFactor')
            ->label('Adicionar fator existente')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('gray')
            ->disabled(fn (): bool => static::getExistingFactorTemplateOptions() === [])
            ->tooltip(fn (): ?string => static::getExistingFactorTemplateOptions() === []
                ? 'Nenhum fator reutilizavel foi encontrado em outros insumos.'
                : null)
            ->schema([
                Select::make('template')
                    ->label('Fator existente')
                    ->options(static::getExistingFactorTemplateOptions())
                    ->searchable()
                    ->required(),
            ])
            ->modalHeading('Adicionar fator existente')
            ->modalDescription('Reaproveite um fator ja usado em outro insumo e ajuste o valor se necessario.')
            ->modalSubmitActionLabel('Adicionar fator')
            ->action(function (array $data, Get $get, Set $set): void {
                $template = static::decodeFactorTemplate($data['template'] ?? null);

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
                    'valor' => $tipo === 'percentual'
                        ? static::formatPercent($valor)
                        : static::formatCurrency($valor),
                ];
            })
            ->values()
            ->all();
    }

    protected static function getExistingFactorTemplateOptions(): array
    {
        return InsumoFatorCusto::query()
            ->orderBy('nome')
            ->orderBy('tipo')
            ->get(['nome', 'tipo', 'valor'])
            ->unique(fn (InsumoFatorCusto $fator): string => implode('|', [
                $fator->nome,
                $fator->tipo,
                number_format((float) $fator->valor, 4, '.', ''),
            ]))
            ->mapWithKeys(fn (InsumoFatorCusto $fator): array => [
                static::encodeFactorTemplate([
                    'nome' => $fator->nome,
                    'tipo' => $fator->tipo,
                    'valor' => (float) $fator->valor,
                ]) => static::formatFactorTemplateLabel($fator),
            ])
            ->all();
    }

    protected static function encodeFactorTemplate(array $template): string
    {
        return base64_encode(json_encode($template) ?: '');
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
            'valor' => (float) ($decoded['valor'] ?? 0),
        ];
    }

    protected static function formatFactorTemplateLabel(InsumoFatorCusto $fator): string
    {
        $tipo = $fator->tipo === 'percentual'
            ? static::formatPercent((float) $fator->valor)
            : static::formatCurrency((float) $fator->valor);

        return "{$fator->nome} ({$tipo})";
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
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    protected static function formatPercent(float|int|null $value): string
    {
        return number_format((float) $value, 2, ',', '.').'%';
    }
}
