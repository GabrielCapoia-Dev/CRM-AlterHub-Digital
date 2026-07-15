<?php

namespace App\Filament\Resources\InsumoMovimentacoes;

use App\Filament\Resources\InsumoMovimentacoes\Pages\ManageInsumoMovimentacoes;
use App\Models\InsumoMovimentacao;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\MovimentacaoEstoqueService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
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

class InsumoMovimentacaoResource extends Resource
{
    protected static ?string $model = InsumoMovimentacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Movimentacoes';

    protected static ?string $navigationParentItem = 'Insumos';

    protected static ?string $modelLabel = 'Movimentacao de insumo';

    protected static ?string $pluralModelLabel = 'Movimentacoes de insumos';

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimentacao')
                    ->description('Historico operacional de entradas, saidas, consumo interno e perdas.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('insumo_id')
                            ->label('Insumo')
                            ->relationship('insumo', 'nome')
                            ->getOptionLabelFromRecordUsing(fn (Insumo $record): string => $record->codigo_interno
                                ? "{$record->codigo_interno} - {$record->nome}"
                                : $record->nome)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if (! $state) {
                                    $set('unidade', null);

                                    return;
                                }

                                $insumo = Insumo::query()
                                    ->with('tipoUnidadeMedida')
                                    ->find($state);

                                if (! $insumo) {
                                    return;
                                }

                                $set('unidade', $insumo->tipoUnidadeMedida?->sigla ?: $insumo->tipoUnidadeMedida?->nome);
                            })
                            ->columnSpanFull(),

                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(InsumoMovimentacao::tipoOptions())
                            ->default('entrada')
                            ->required()
                            ->live()
                            ->columnSpan(4),

                        TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0.0001)
                            ->required()
                            ->helperText('Entradas somam ao estoque. Saidas, consumo interno e perdas reduzem o saldo informado.')
                            ->live()
                            ->columnSpan(4),

                        TextInput::make('unidade')
                            ->label('Unidade')
                            ->readOnly()
                            ->maxLength(50)
                            ->placeholder('Definida pelo insumo selecionado')
                            ->helperText('A unidade segue o cadastro padrao do insumo e nao pode ser alterada aqui.')
                            ->columnSpan(4),

                        DatePicker::make('realizado_em')
                            ->label('Realizado em')
                            ->default(now())
                            ->required()
                            ->columnSpan(4),

                        Select::make('user_id')
                            ->label('Responsavel')
                            ->relationship('user', 'name')
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(4),

                        TextInput::make('documento_referencia')
                            ->label('Documento / referencia')
                            ->maxLength(255)
                            ->placeholder('NF, ordem interna, requisicao...')
                            ->columnSpan(4),

                        View::make('filament.resources.insumo-movimentacoes.forms.financial-summary')
                            ->columnSpanFull()
                            ->viewData(fn (Get $get): array => [
                                'summary' => static::buildFinancialSummary($get),
                            ]),

                        TextInput::make('origem_destino')
                            ->label('Origem / contexto')
                            ->maxLength(255)
                            ->placeholder('Laboratorio, almoxarifado, setor...')
                            ->columnSpan(6),

                        TextInput::make('destino')
                            ->label('Destino')
                            ->maxLength(255)
                            ->placeholder('Informe o destino quando aplicavel')
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->columnSpan(6),

                        TextInput::make('lote')
                            ->label('Lote')
                            ->maxLength(255)
                            ->placeholder('Lote / serie')
                            ->visible(fn (Get $get): bool => $get('tipo') === 'entrada')
                            ->columnSpan(4),

                        TextInput::make('motivo')
                            ->label('Motivo')
                            ->maxLength(255)
                            ->placeholder('Obrigatorio para saida, consumo interno e perda')
                            ->required(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->columnSpanFull(),

                        Textarea::make('observacao')
                            ->label('Observacao operacional')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('observacao_interna')
                            ->label('Observacao interna')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['insumo', 'user']))
            ->columns([
                Split::make([
                    TextColumn::make('realizado_em')
                        ->label('Data')
                        ->description('Data', position: 'above')
                        ->date('d/m/Y')
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    Stack::make([
                        TextColumn::make('insumo.nome')
                            ->label('Insumo')
                            ->searchable()
                            ->weight('semibold')
                            ->description(fn (InsumoMovimentacao $record): ?string => $record->unidade)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('insumo.codigo_interno')
                            ->label('Codigo')
                            ->fontFamily('mono')
                            ->searchable()
                            ->toggleable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),
                    ]),

                    TextColumn::make('tipo')
                        ->label('Tipo')
                        ->badge()
                        ->color(fn (?string $state): string => InsumoMovimentacao::tipoColors()[$state] ?? 'gray')
                        ->formatStateUsing(fn (?string $state): string => InsumoMovimentacao::tipoOptions()[$state] ?? 'Nao definido')
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
                        TextColumn::make('quantidade')
                            ->label('Qtd.')
                            ->description('Quantidade', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): string => static::formatQuantity($state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('impacto_estoque')
                            ->label('Impacto')
                            ->description('Impacto', position: 'above')
                            ->badge()
                            ->alignEnd()
                            ->color(function ($state): string {
                                $value = (float) $state;

                                if ($value > 0) {
                                    return 'success';
                                }

                                if ($value < 0) {
                                    return 'danger';
                                }

                                return 'gray';
                            })
                            ->formatStateUsing(function ($state): string {
                                $value = (float) $state;
                                $prefix = $value > 0 ? '+' : '';

                                return $prefix.static::formatQuantity($value);
                            })
                            ->extraAttributes(['class' => 'crm-list-field crm-list-impact'], merge: true),

                        TextColumn::make('saldo_atual')
                            ->label('Saldo')
                            ->description('Saldo', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): string => static::formatQuantity($state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('documento_referencia')
                            ->label('Documento')
                            ->description('Documento', position: 'above')
                            ->limit(28)
                            ->placeholder('-')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),

                TextColumn::make('responsavel_nome')
                    ->label('Responsavel')
                    ->description('Responsavel', position: 'above')
                    ->formatStateUsing(fn (?string $state, InsumoMovimentacao $record): string => $state ?: ($record->user?->name ?? '-'))
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('realizado_em', 'desc')
            ->searchPlaceholder('Buscar por codigo, insumo ou documento...')
            ->filters([
                SelectFilter::make('insumo_id')
                    ->label('Insumo')
                    ->relationship('insumo', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(InsumoMovimentacao::tipoOptions()),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--movement')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Visualizar')
                        ->slideOver(),
                    Action::make('estornar')
                        ->label('Estornar')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('O razao e imutavel. Sera criada uma movimentacao compensatoria vinculada a esta.')
                        ->schema([
                            Textarea::make('justificativa')
                                ->label('Justificativa')
                                ->required()
                                ->minLength(10),
                        ])
                        ->visible(fn (InsumoMovimentacao $record): bool => ! $record->estornada_em
                            && ! $record->estorno_de_id
                            && app(MovimentacaoEstoqueService::class)->podeEstornarDiretamente($record))
                        ->authorize(fn (): bool => auth()->user()?->can('create', InsumoMovimentacao::class) ?? false)
                        ->action(function (InsumoMovimentacao $record, array $data): void {
                            app(MovimentacaoEstoqueService::class)->estornarInsumo(
                                $record,
                                auth()->user(),
                                $data['justificativa'],
                                "estorno:insumo:{$record->id}",
                            );
                            Notification::make()->title('Estorno registrado no razao')->success()->send();
                        }),
                ])
                    ->label('Ações')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInsumoMovimentacoes::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Nova movimentacao')
            ->modalWidth('5xl')
            ->slideOver()
            ->createAnother(false)
            ->modalHeading('Registrar movimentacao de insumo')
            ->modalDescription('O saldo do item e recalculado automaticamente a partir desta operacao.')
            ->modalSubmitActionLabel('Registrar movimentacao')
            ->using(fn (array $data, string $model): InsumoMovimentacao => app(MovimentacaoEstoqueService::class)
                ->createForInsumo($data, auth()->user()));
    }

    protected static function formatQuantity(float|int|string|null $value): string
    {
        return NumericFormat::decimal($value);
    }

    protected static function formatCurrency(float|int|null $value): string
    {
        return NumericFormat::money($value);
    }

    protected static function buildFinancialSummary(Get $get): array
    {
        $insumoId = $get('insumo_id');

        if (! $insumoId) {
            return [
                'has_insumo' => false,
                'message' => 'Selecione um insumo para ver o custo unitario, cambio, fatores e o impacto financeiro desta movimentacao.',
            ];
        }

        $insumo = Insumo::query()
            ->with(['tipoUnidadeMedida', 'insumoFatoresCusto'])
            ->withSum('insumoMovimentacoes as estoque_atual', 'impacto_estoque')
            ->find($insumoId);

        if (! $insumo) {
            return [
                'has_insumo' => false,
                'message' => 'Insumo nao encontrado para montar o resumo financeiro.',
            ];
        }

        $origemMoeda = $insumo->origem === 'importado'
            ? trim(($insumo->moeda_origem ?? '-').' '.NumericFormat::decimal($insumo->custo_moeda_origem ?? 0))
            : static::formatCurrency((float) ($insumo->custo_referencia ?? 0));
        $quantidade = (float) ($get('quantidade') ?? 0);
        $tipo = $get('tipo') ?? 'entrada';
        $quantidadeFinanceira = static::resolveFinancialQuantity($quantidade);
        $custoFinalUnitario = $insumo->finalCostAmount();
        $impactoTotal = round($quantidadeFinanceira * $custoFinalUnitario, 4);
        $unidade = $get('unidade') ?: ($insumo->tipoUnidadeMedida?->sigla ?: $insumo->tipoUnidadeMedida?->nome ?: 'un');

        return [
            'has_insumo' => true,
            'origem' => $insumo->origem,
            'tipo_label' => InsumoMovimentacao::tipoOptions()[$tipo] ?? 'Movimentacao',
            'valor_origem' => $origemMoeda,
            'cambio' => $insumo->origem === 'importado'
                ? NumericFormat::decimal($insumo->taxa_cambio ?? 0)
                : 'Nao se aplica',
            'custo_efetivo' => static::formatCurrency($insumo->effectiveCostAmount()),
            'custo_final' => static::formatCurrency($custoFinalUnitario),
            'quantidade' => trim(NumericFormat::decimal($quantidade).' '.$unidade),
            'quantidade_financeira' => trim(NumericFormat::decimal($quantidadeFinanceira).' '.$unidade),
            'impacto_total' => static::formatCurrency($impactoTotal),
            'impacto_formula' => NumericFormat::decimal($quantidadeFinanceira).' x '.static::formatCurrency($custoFinalUnitario),
            'estoque_atual' => $insumo->estoqueAtual() === null
                ? 'Sem historico'
                : trim(NumericFormat::decimal($insumo->estoqueAtual()).' '.$unidade),
            'fatores' => static::buildFinancialFactorLines($insumo),
        ];
    }

    protected static function buildFinancialFactorLines(Insumo $insumo): array
    {
        return $insumo->insumoFatoresCusto
            ->map(function ($fator): array {
                $valor = (float) $fator->valor;
                $tipo = $fator->tipo;

                return [
                    'nome' => $fator->nome,
                    'tipo' => $tipo === 'percentual' ? 'Percentual sobre a base' : 'Valor fixo em BRL',
                    'valor' => $tipo === 'percentual'
                        ? ($valor > 0 && $valor < 1 ? NumericFormat::decimal($valor) : NumericFormat::percent($valor))
                        : static::formatCurrency($valor),
                ];
            })
            ->all();
    }

    protected static function resolveFinancialQuantity(float $quantidade): float
    {
        return max($quantidade, 0);
    }
}
