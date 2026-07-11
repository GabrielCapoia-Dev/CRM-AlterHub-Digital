<?php

namespace App\Filament\Resources\VendasOperacao;

use App\Filament\Resources\VendasOperacao\Pages\ManageVendasOperacao;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VendaOperacaoResource extends Resource
{
    protected static ?string $model = VendaOperacaoPedido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Vendas';

    protected static ?string $modelLabel = 'Venda';

    protected static ?string $pluralModelLabel = 'Vendas';

    public static ?string $slug = 'operacao/vendas';

    protected static string|UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return app(VendaOperacaoService::class)
            ->queryPorPerfil(auth()->user())
            ->with(['cliente', 'oportunidade', 'vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'aprovadoPor']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getSaleFormComponents());
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Component>
     */
    public static function getSaleFormComponents(): array
    {
        return [
            Section::make('Venda')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->options(fn (): array => Cliente::query()
                            ->orderBy('razao_social')
                            ->get()
                            ->mapWithKeys(fn (Cliente $cliente): array => [
                                $cliente->id => $cliente->codigo_interno
                                    ? "{$cliente->codigo_interno} - {$cliente->razao_social}"
                                    : $cliente->razao_social,
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),

                    DatePicker::make('data_venda')
                        ->label('Data')
                        ->default(now())
                        ->required(),

                    TextInput::make('vendedor_nome')
                        ->label('Vendedor')
                        ->default(fn (): ?string => auth()->user()?->name)
                        ->maxLength(255)
                        ->columnSpan(1),

                    Textarea::make('observacao')
                        ->label('Observacao')
                        ->rows(2)
                        ->columnSpanFull(),

                    Hidden::make('origem_pedido_id'),
                ]),

            Section::make('Produtos')
                ->description('Preco abaixo do minimo envia a venda para aprovacao.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('itens')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Adicionar produto')
                        ->reorderable(false)
                        ->columns(12)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('produto_id')
                                ->label('Produto')
                                ->options(fn (): array => Produto::query()
                                    ->where('status', 'ativo')
                                    ->where('ativo', true)
                                    ->orderBy('nome')
                                    ->get()
                                    ->mapWithKeys(fn (Produto $produto): array => [
                                        $produto->id => $produto->codigo_interno
                                            ? "{$produto->codigo_interno} - {$produto->nome}"
                                            : $produto->nome,
                                    ])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if (! $state) {
                                        $set('preco_unitario', null);
                                        $set('desconto_percentual', 0);

                                        return;
                                    }

                                    $produto = Produto::query()->find($state);

                                    if (! $produto) {
                                        return;
                                    }

                                    $tabela = $produto->preco_tabela !== null
                                        ? round((float) $produto->preco_tabela, 2)
                                        : null;

                                    $set('preco_unitario', $tabela !== null
                                        ? number_format($tabela, 2, '.', '')
                                        : null);
                                    $set('desconto_percentual', 0);
                                })
                                ->columnSpan(12),

                            Placeholder::make('produto_contexto')
                                ->hiddenLabel()
                                ->content(function (Get $get): string {
                                    $produtoId = $get('produto_id');

                                    if (! $produtoId) {
                                        return 'Selecione um produto para ver estoque e precos.';
                                    }

                                    $produto = Produto::query()
                                        ->with('produtoMovimentacoes')
                                        ->find($produtoId);

                                    if (! $produto) {
                                        return 'Produto nao encontrado.';
                                    }

                                    $estoque = (float) ($produto->estoqueAtual() ?? 0);
                                    $tabela = $produto->preco_tabela !== null
                                        ? NumericFormat::money((float) $produto->preco_tabela)
                                        : '-';
                                    $minimo = $produto->preco_minimo !== null
                                        ? NumericFormat::money((float) $produto->preco_minimo)
                                        : '-';

                                    return sprintf(
                                        'Estoque: %s %s · Tabela: %s · Minimo: %s',
                                        NumericFormat::decimal($estoque),
                                        $produto->unidade_medida ?: 'un',
                                        $tabela,
                                        $minimo,
                                    );
                                })
                                ->columnSpan(12),

                            TextInput::make('quantidade')
                                ->label('Qtd.')
                                ->numeric()
                                ->rule('decimal:0,4')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                ->minValue(0.0001)
                                ->placeholder('1')
                                ->required()
                                ->columnSpan(3),

                            TextInput::make('desconto_percentual')
                                ->label('Desconto %')
                                ->numeric()
                                ->rule('decimal:0,2')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state ?? 0))
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                    static::syncPrecoFromDesconto($get, $set, $state);
                                })
                                ->columnSpan(3),

                            TextInput::make('preco_unitario')
                                ->label('Preco unit.')
                                ->numeric()
                                ->rule('decimal:0,2')
                                ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                ->prefix('R$')
                                ->minValue(0.01)
                                ->placeholder('0,00')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                    static::syncDescontoFromPreco($get, $set, $state);
                                })
                                ->columnSpan(3),

                            Placeholder::make('alerta_aprovacao')
                                ->label('Status')
                                ->content(function (Get $get): string {
                                    $produtoId = $get('produto_id');
                                    $preco = (float) ($get('preco_unitario') ?? 0);

                                    if (! $produtoId || $preco <= 0) {
                                        return '—';
                                    }

                                    $produto = Produto::query()->find($produtoId);
                                    $minimo = $produto?->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;

                                    if ($minimo > 0 && $preco < $minimo) {
                                        return 'Abaixo do minimo — vai para aprovacao';
                                    }

                                    return 'Ok';
                                })
                                ->columnSpan(3),
                        ]),
                ]),
        ];
    }

    protected static function syncPrecoFromDesconto(Get $get, Set $set, mixed $descontoState): void
    {
        $produtoId = $get('produto_id');

        if (! $produtoId) {
            return;
        }

        $produto = Produto::query()->find($produtoId);
        $tabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;

        if ($tabela <= 0) {
            return;
        }

        $desconto = min(max((float) ($descontoState ?? 0), 0), 100);
        $preco = round($tabela * (1 - ($desconto / 100)), 2);

        $set('desconto_percentual', number_format($desconto, 2, '.', ''));
        $set('preco_unitario', number_format(max($preco, 0.01), 2, '.', ''));
    }

    protected static function syncDescontoFromPreco(Get $get, Set $set, mixed $precoState): void
    {
        $produtoId = $get('produto_id');

        if (! $produtoId) {
            return;
        }

        $produto = Produto::query()->find($produtoId);
        $tabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;
        $preco = (float) ($precoState ?? 0);

        if ($tabela <= 0 || $preco <= 0) {
            $set('desconto_percentual', '0.00');

            return;
        }

        $desconto = round(max(0, min(100, (1 - ($preco / $tabela)) * 100)), 2);
        $set('desconto_percentual', number_format($desconto, 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'cliente',
                'oportunidade',
                'vendasOperacao.produto',
                'vendasOperacao.produtoMovimentacao',
                'aprovadoPor',
            ]))
            ->columns([
                Split::make([
                    TextColumn::make('data_venda')
                        ->label('Data')
                        ->description('Data', position: 'above')
                        ->date('d/m/Y')
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    Stack::make([
                        TextColumn::make('codigo')
                            ->label('Venda')
                            ->searchable()
                            ->weight('semibold')
                            ->description(fn (VendaOperacaoPedido $record): ?string => $record->oportunidade?->titulo)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('cliente_nome_snapshot')
                            ->label('Cliente')
                            ->searchable()
                            ->placeholder('-')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('lucro_apos_impostos_total')
                        ->label('Lucro')
                        ->description('Lucro', position: 'above')
                        ->money('BRL')
                        ->sortable()
                        ->alignEnd()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 4,
                ])
                    ->schema([
                        TextColumn::make('status')
                            ->label('Status')
                            ->description('Status', position: 'above')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => VendaOperacaoPedido::statusOptions()[$state] ?? 'Ativa')
                            ->color(fn (?string $state): string => match ($state) {
                                VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO => 'warning',
                                VendaOperacaoPedido::STATUS_RECUSADA => 'danger',
                                VendaOperacaoPedido::STATUS_CANCELADA => 'gray',
                                default => 'success',
                            })
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('itens_count')
                            ->label('Itens')
                            ->description('Itens', position: 'above')
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('quantidade_total')
                            ->label('Qtd. total')
                            ->description('Qtd. total', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): string => NumericFormat::decimal($state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('receita_bruta_total')
                            ->label('Receita')
                            ->description('Receita', position: 'above')
                            ->money('BRL')
                            ->sortable()
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),

                        TextColumn::make('custo_total_snapshot')
                            ->label('Custo')
                            ->description('Custo', position: 'above')
                            ->money('BRL')
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-finance']),

                TextColumn::make('vendedor_nome_snapshot')
                    ->label('Vendedor')
                    ->description('Vendedor', position: 'above')
                    ->searchable()
                    ->placeholder('-')
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('data_venda', 'desc')
            ->searchPlaceholder('Buscar por venda, cliente, vendedor ou oportunidade...')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VendaOperacaoPedido::statusOptions()),

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

                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->options(fn (): array => Produto::query()
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $produtoId = $data['value'] ?? null;

                        if (! $produtoId) {
                            return $query;
                        }

                        return $query->whereHas('vendasOperacao', fn (Builder $builder) => $builder->where('produto_id', $produtoId));
                    }),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--operation')
            ->recordActions([
                Action::make('products')
                    ->label('Visualizar produtos')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Produtos da venda '.$record->codigo)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->extraModalWindowAttributes([
                        'class' => 'oa-record-modal oa-sale-products-modal',
                    ])
                    ->modalContent(fn (VendaOperacaoPedido $record): View => view('filament.resources.vendas-operacao.modals.products', [
                        'pedido' => $record->load(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'aprovadoPor']),
                    ])),

                Action::make('copy')
                    ->label('Copiar venda')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->modalWidth('5xl')
                    ->slideOver(false)
                    ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Copiar venda '.$record->codigo)
                    ->modalDescription('Dados copiados com precos de tabela, sem desconto. Ajuste se precisar e registre.')
                    ->modalSubmitActionLabel('Registrar venda copiada')
                    ->extraModalWindowAttributes([
                        'class' => 'oa-record-modal oa-sales-modal',
                    ])
                    ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('copy', $record) ?? false)
                    ->schema(static::getSaleFormComponents())
                    ->fillForm(fn (VendaOperacaoPedido $record): array => app(VendaOperacaoService::class)->buildCopyPayload($record))
                    ->action(function (array $data): void {
                        try {
                            $pedido = app(VendaOperacaoService::class)->createPedido($data, auth()->user());
                            static::notifySaleCreated($pedido);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Nao foi possivel copiar a venda')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();

                            throw $exception;
                        }
                    }),

                Action::make('approveDiscount')
                    ->label('Aprovar desconto')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar desconto da venda')
                    ->modalDescription('A venda sera confirmada e o estoque sera baixado agora.')
                    ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('approveDiscount', $record) ?? false)
                    ->action(function (VendaOperacaoPedido $record): void {
                        try {
                            app(VendaOperacaoService::class)->approve($record, auth()->user());

                            Notification::make()
                                ->title('Desconto aprovado')
                                ->body('A venda foi confirmada e o estoque foi atualizado.')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Nao foi possivel aprovar')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('rejectDiscount')
                    ->label('Recusar desconto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('rejectDiscount', $record) ?? false)
                    ->form([
                        Textarea::make('motivo_recusa')
                            ->label('Motivo da recusa')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (VendaOperacaoPedido $record, array $data): void {
                        try {
                            app(VendaOperacaoService::class)->reject(
                                $record,
                                auth()->user(),
                                $data['motivo_recusa'] ?? null,
                            );

                            Notification::make()
                                ->title('Desconto recusado')
                                ->body('A venda foi marcada como recusada. Nenhum estoque foi movimentado.')
                                ->warning()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Nao foi possivel recusar')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendasOperacao::route('/'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->label('Nova venda')
            ->modalWidth('5xl')
            ->slideOver(false)
            ->createAnother(false)
            ->modalHeading('Nova venda')
            ->modalDescription('Informe cliente, produtos, quantidade e desconto. Preco abaixo do minimo aguarda aprovacao.')
            ->modalSubmitActionLabel('Registrar venda')
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-sales-modal',
            ])
            ->using(function (array $data): VendaOperacaoPedido {
                $pedido = app(VendaOperacaoService::class)->createPedido($data, auth()->user());
                static::notifySaleCreated($pedido);

                return $pedido;
            });
    }

    protected static function notifySaleCreated(VendaOperacaoPedido $pedido): void
    {
        if ($pedido->isPendenteAprovacao()) {
            Notification::make()
                ->title('Venda enviada para aprovacao')
                ->body('Ha item com preco abaixo do minimo. O estoque so sera baixado apos autorizacao.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Venda registrada')
            ->body('Estoque atualizado e snapshots financeiros gravados.')
            ->success()
            ->send();
    }
}
