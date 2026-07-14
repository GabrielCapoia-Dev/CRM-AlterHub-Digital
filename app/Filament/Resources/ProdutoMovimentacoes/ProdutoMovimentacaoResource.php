<?php

namespace App\Filament\Resources\ProdutoMovimentacoes;

use App\Filament\Resources\ProdutoMovimentacoes\Pages\ManageProdutoMovimentacoes;
use App\Models\Empresas\Fornecedor;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Services\Produtos\MovimentacaoEstoqueService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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

class ProdutoMovimentacaoResource extends Resource
{
    protected static ?string $model = ProdutoMovimentacao::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Movimentacoes';

    protected static ?string $navigationParentItem = 'Produtos';

    protected static ?string $modelLabel = 'Movimentacao de produto';

    protected static ?string $pluralModelLabel = 'Movimentacoes de produtos';

    protected static string | UnitEnum | null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimentacao')
                    ->description('Historico geral de entradas, saidas, consumo interno e perdas dos produtos.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('produto_id')
                            ->label('Produto')
                            ->relationship('produto', 'nome')
                            ->getOptionLabelFromRecordUsing(fn (Produto $record): string => $record->codigo_interno
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

                                $produto = Produto::query()->find($state);

                                if (! $produto) {
                                    return;
                                }

                                $set('unidade', $produto->unidade_medida);
                            })
                            ->columnSpanFull(),

                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(ProdutoMovimentacao::tipoOptions())
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
                            ->placeholder('Definida pelo produto selecionado')
                            ->helperText('A unidade segue o cadastro padrao do produto e nao pode ser alterada aqui.')
                            ->columnSpan(4),

                        DatePicker::make('realizado_em')
                            ->label('Realizado em')
                            ->default(now())
                            ->required()
                            ->columnSpan(3),

                        Select::make('user_id')
                            ->label('Responsavel')
                            ->relationship('user', 'name')
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(3),

                        Select::make('fornecedor_id')
                            ->label('Fornecedor')
                            ->relationship('fornecedor', 'razao_social', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('razao_social'))
                            ->getOptionLabelFromRecordUsing(fn (Fornecedor $record): string => $record->codigo_interno
                                ? "{$record->codigo_interno} - {$record->razao_social}"
                                : $record->razao_social)
                            ->searchable(['codigo_interno', 'razao_social', 'nome_fantasia', 'cnpj'])
                            ->preload()
                            ->columnSpan(3),

                        TextInput::make('documento_referencia')
                            ->label('Documento / referencia')
                            ->maxLength(255)
                            ->placeholder('NF, ordem interna, requisicao...')
                            ->columnSpan(3),

                        View::make('filament.resources.produto-movimentacoes.forms.financial-summary')
                            ->columnSpanFull()
                            ->viewData(fn (Get $get): array => [
                                'summary' => static::buildFinancialSummary($get),
                            ]),

                        TextInput::make('destino')
                            ->label('Destino')
                            ->maxLength(255)
                            ->placeholder('Informe o destino quando aplicavel')
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->columnSpan(6),

                        TextInput::make('motivo')
                            ->label('Motivo')
                            ->maxLength(255)
                            ->placeholder('Obrigatorio para saida, consumo interno e perda')
                            ->required(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'consumo_interno', 'perda'], true))
                            ->columnSpanFull(),

                        Textarea::make('observacao')
                            ->label('Observacao')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['produto', 'user', 'fornecedor']))
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
                        TextColumn::make('produto.nome')
                            ->label('Produto')
                            ->searchable()
                            ->weight('semibold')
                            ->description(fn (ProdutoMovimentacao $record): ?string => $record->unidade)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('produto.codigo_interno')
                            ->label('Codigo')
                            ->fontFamily('mono')
                            ->searchable()
                            ->toggleable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),
                    ]),

                    TextColumn::make('tipo')
                        ->label('Tipo')
                        ->badge()
                        ->color(fn (?string $state): string => ProdutoMovimentacao::tipoColors()[$state] ?? 'gray')
                        ->formatStateUsing(fn (?string $state): string => ProdutoMovimentacao::tipoOptions()[$state] ?? 'Nao definido')
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 5,
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

                                return $prefix . static::formatQuantity($value);
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

                        TextColumn::make('fornecedor.razao_social')
                            ->label('Fornecedor')
                            ->description('Fornecedor', position: 'above')
                            ->limit(28)
                            ->placeholder('-')
                            ->searchable()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),

                TextColumn::make('responsavel_nome')
                    ->label('Responsavel')
                    ->description('Responsavel', position: 'above')
                    ->formatStateUsing(fn (?string $state, ProdutoMovimentacao $record): string => $state ?: ($record->user?->name ?? '-'))
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('realizado_em', 'desc')
            ->searchPlaceholder('Buscar por codigo, produto ou documento...')
            ->filters([
                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->relationship('produto', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(ProdutoMovimentacao::tipoOptions()),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--movement')
            ->recordActions([
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
                    ->visible(fn (ProdutoMovimentacao $record): bool => ! $record->estornada_em && ! $record->estorno_de_id)
                    ->authorize(fn (): bool => auth()->user()?->can('create', ProdutoMovimentacao::class) ?? false)
                    ->action(function (ProdutoMovimentacao $record, array $data): void {
                        app(MovimentacaoEstoqueService::class)->estornarProduto(
                            $record,
                            auth()->user(),
                            $data['justificativa'],
                            "estorno:produto:{$record->id}",
                        );
                        Notification::make()->title('Estorno registrado no razao')->success()->send();
                    }),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProdutoMovimentacoes::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Nova movimentacao')
            ->modalWidth('5xl')
            ->slideOver()
            ->createAnother(false)
            ->modalHeading('Registrar movimentacao de produto')
            ->modalDescription('O saldo do produto e recalculado automaticamente a partir desta operacao.')
            ->modalSubmitActionLabel('Registrar movimentacao')
            ->using(fn (array $data, string $model): ProdutoMovimentacao => app(MovimentacaoEstoqueService::class)
                ->createForProduto($data, auth()->user()));
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
        $produtoId = $get('produto_id');

        if (! $produtoId) {
            return [
                'has_produto' => false,
                'message' => 'Selecione um produto para ver o custo base, os precos de referencia e o impacto financeiro desta movimentacao.',
            ];
        }

        $produto = Produto::query()
            ->withSum('produtoMovimentacoes as estoque_atual', 'impacto_estoque')
            ->find($produtoId);

        if (! $produto) {
            return [
                'has_produto' => false,
                'message' => 'Produto nao encontrado para montar o resumo financeiro.',
            ];
        }

        $quantidade = (float) ($get('quantidade') ?? 0);
        $tipo = $get('tipo') ?? 'entrada';
        $quantidadeFinanceira = static::resolveFinancialQuantity($quantidade);
        $custoBase = (float) ($produto->custo_base_formacao ?? 0);
        $impactoTotal = round($quantidadeFinanceira * $custoBase, 4);
        $unidade = $get('unidade') ?: ($produto->unidade_medida ?: 'un');

        return [
            'has_produto' => true,
            'tipo_label' => ProdutoMovimentacao::tipoOptions()[$tipo] ?? 'Movimentacao',
            'custo_base' => static::formatCurrency($custoBase),
            'preco_sugerido' => $produto->preco_sugerido !== null
                ? static::formatCurrency((float) $produto->preco_sugerido)
                : 'Nao definido',
            'preco_tabela' => $produto->preco_tabela !== null
                ? static::formatCurrency((float) $produto->preco_tabela)
                : 'Nao definido',
            'preco_minimo' => $produto->preco_minimo !== null
                ? static::formatCurrency((float) $produto->preco_minimo)
                : 'Nao definido',
            'status' => Produto::statusOptions()[$produto->status] ?? 'Nao definido',
            'quantidade' => trim(NumericFormat::decimal($quantidade) . ' ' . $unidade),
            'quantidade_financeira' => trim(NumericFormat::decimal($quantidadeFinanceira) . ' ' . $unidade),
            'impacto_total' => static::formatCurrency($impactoTotal),
            'impacto_formula' => NumericFormat::decimal($quantidadeFinanceira) . ' x ' . static::formatCurrency($custoBase),
            'estoque_atual' => $produto->estoqueAtual() === null
                ? 'Sem historico'
                : trim(NumericFormat::decimal($produto->estoqueAtual()) . ' ' . $unidade),
        ];
    }

    protected static function resolveFinancialQuantity(float $quantidade): float
    {
        return max($quantidade, 0);
    }
}
