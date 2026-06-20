<?php

namespace App\Filament\Resources\VendasOperacao;

use App\Filament\Resources\VendasOperacao\Pages\ManageVendasOperacao;
use App\Models\Produto;
use App\Models\VendaOperacao;
use App\Services\Operacao\OperacaoAnalyticsService;
use App\Services\Operacao\VendaOperacaoService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VendaOperacaoResource extends Resource
{
    protected static ?string $model = VendaOperacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Vendas (estoque)';

    protected static ?string $modelLabel = 'Venda operacional';

    protected static ?string $pluralModelLabel = 'Vendas operacionais';

    public static ?string $slug = 'operacao/vendas';

    protected static string|UnitEnum|null $navigationGroup = 'Operacao';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Venda')
                    ->description('Baixa de estoque com custo medio automatico e snapshots financeiros da operacao.')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->columns(2)
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
                            ->columnSpanFull(),

                        Placeholder::make('estoque_atual_preview')
                            ->label('Estoque atual')
                            ->content(function (Get $get): string {
                                $produtoId = $get('produto_id');

                                if (! $produtoId) {
                                    return 'Selecione um produto.';
                                }

                                $produto = Produto::query()
                                    ->with('produtoMovimentacoes')
                                    ->find($produtoId);

                                if (! $produto) {
                                    return 'Produto nao encontrado.';
                                }

                                $estoque = (float) ($produto->estoqueAtual() ?? 0);

                                return NumericFormat::decimal($estoque) . ' ' . ($produto->unidade_medida ?: 'un');
                            }),

                        Placeholder::make('custo_medio_preview')
                            ->label('Custo medio atual')
                            ->content(function (Get $get): string {
                                $produtoId = $get('produto_id');

                                if (! $produtoId) {
                                    return 'Selecione um produto.';
                                }

                                $produto = Produto::query()
                                    ->with('produtoMovimentacoes')
                                    ->find($produtoId);

                                if (! $produto) {
                                    return 'Produto nao encontrado.';
                                }

                                $custo = app(OperacaoAnalyticsService::class)->currentAverageCostForProduct($produto);

                                return NumericFormat::money($custo);
                            }),

                        TextInput::make('quantidade')
                            ->label('Quantidade')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0.0001)
                            ->placeholder('0,00')
                            ->required(),

                        TextInput::make('preco_unitario')
                            ->label('Preco venda unit.')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->prefix('R$')
                            ->minValue(0.01)
                            ->placeholder('0,00')
                            ->required(),

                        TextInput::make('icms_aliquota')
                            ->label('ICMS %')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->default(0)
                            ->minValue(0),

                        TextInput::make('outros_impostos_aliquota')
                            ->label('Outros impostos %')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->default(0)
                            ->minValue(0),

                        DatePicker::make('data_venda')
                            ->label('Data')
                            ->default(now())
                            ->required(),

                        TextInput::make('cliente_nome')
                            ->label('Cliente')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('vendedor_nome')
                            ->label('Vendedor')
                            ->maxLength(255)
                            ->columnSpanFull(),

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
                Split::make([
                    TextColumn::make('data_venda')
                        ->label('Data')
                        ->description('Data', position: 'above')
                        ->date('d/m/Y')
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    Stack::make([
                        TextColumn::make('produto_nome_snapshot')
                            ->label('Produto')
                            ->searchable(['produto_nome_snapshot', 'produto_codigo_snapshot'])
                            ->weight('semibold')
                            ->description(fn (VendaOperacao $record): ?string => $record->produto_codigo_snapshot)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('cliente_nome')
                            ->label('Cliente')
                            ->searchable()
                            ->placeholder('-')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('lucro_apos_impostos')
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
                        TextColumn::make('quantidade')
                            ->label('Qtd.')
                            ->description('Quantidade', position: 'above')
                            ->alignEnd()
                            ->formatStateUsing(fn ($state): string => NumericFormat::decimal($state))
                            ->extraAttributes(['class' => 'crm-list-field crm-list-number'], merge: true),

                        TextColumn::make('preco_unitario')
                            ->label('Preco un.')
                            ->description('Preco un.', position: 'above')
                            ->money('BRL')
                            ->alignEnd()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),

                        TextColumn::make('receita_bruta')
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

                TextColumn::make('vendedor_nome')
                    ->label('Vendedor')
                    ->description('Vendedor', position: 'above')
                    ->searchable()
                    ->placeholder('-')
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('data_venda', 'desc')
            ->searchPlaceholder('Buscar por cliente, vendedor ou SKU...')
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

                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->relationship('produto', 'nome')
                    ->searchable()
                    ->preload(),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--operation')
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->slideOver()
                    ->modalWidth('4xl'),
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
            ->modalDescription('A venda reduz o estoque do produto e grava os snapshots de custo, receita e impostos.')
            ->modalSubmitActionLabel('Registrar venda')
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-sales-modal',
            ])
            ->using(fn (array $data): VendaOperacao => app(VendaOperacaoService::class)->create($data, auth()->user()));
    }
}
