<?php

namespace App\Filament\Resources\ProdutoMovimentacoes;

use App\Filament\Resources\ProdutoMovimentacoes\Pages\ManageProdutoMovimentacoes;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Services\Produtos\MovimentacaoEstoqueService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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
                    ->description('Historico geral de entradas, saidas e ajustes dos produtos.')
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
                            ->helperText(fn (Get $get): string => $get('tipo') === 'ajuste'
                                ? 'Em ajustes, a quantidade informada passa a ser o novo saldo do produto.'
                                : 'Quantidade movimentada nesta operacao.')
                            ->live()
                            ->columnSpan(4),

                        TextInput::make('unidade')
                            ->label('Unidade')
                            ->maxLength(50)
                            ->placeholder('Ex.: un, kit, cx')
                            ->columnSpan(4),

                        DateTimePicker::make('realizado_em')
                            ->label('Realizado em')
                            ->default(now())
                            ->seconds(false)
                            ->required()
                            ->columnSpan(4),

                        TextInput::make('responsavel_nome')
                            ->label('Responsavel')
                            ->default(fn (): ?string => auth()->user()?->name)
                            ->maxLength(255)
                            ->columnSpan(4),

                        TextInput::make('documento_referencia')
                            ->label('Documento / referencia')
                            ->maxLength(255)
                            ->placeholder('NF, pedido, romaneio...')
                            ->columnSpan(4),

                        TextInput::make('origem_destino')
                            ->label('Origem / contexto')
                            ->maxLength(255)
                            ->placeholder('Loja, estoque, operacao...')
                            ->columnSpan(6),

                        TextInput::make('destino')
                            ->label('Destino')
                            ->maxLength(255)
                            ->placeholder('Informe o destino quando aplicavel')
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'transferencia', 'consumo_interno', 'perda'], true))
                            ->columnSpan(6),

                        TextInput::make('valor_unitario')
                            ->label('Valor unitario')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->minValue(0)
                            ->prefix('R$')
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => $get('tipo') === 'entrada')
                            ->live()
                            ->columnSpan(4),

                        Placeholder::make('valor_total_preview')
                            ->label('Valor total previsto')
                            ->content(fn (Get $get): string => static::formatCurrency(
                                ((float) ($get('quantidade') ?? 0)) * ((float) ($get('valor_unitario') ?? 0))
                            ))
                            ->visible(fn (Get $get): bool => $get('tipo') === 'entrada')
                            ->columnSpan(4),

                        TextInput::make('motivo')
                            ->label('Motivo')
                            ->maxLength(255)
                            ->placeholder('Obrigatorio para saida, ajuste, consumo e perda')
                            ->visible(fn (Get $get): bool => in_array($get('tipo'), ['saida', 'ajuste', 'consumo_interno', 'perda'], true))
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['produto', 'user']))
            ->columns([
                TextColumn::make('realizado_em')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('produto.codigo_interno')
                    ->label('Codigo')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('produto.nome')
                    ->label('Produto')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (ProdutoMovimentacao $record): ?string => $record->unidade),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state): string => ProdutoMovimentacao::tipoColors()[$state] ?? 'gray')
                    ->formatStateUsing(fn (?string $state): string => ProdutoMovimentacao::tipoOptions()[$state] ?? 'Nao definido'),

                TextColumn::make('quantidade')
                    ->label('Qtd.')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => static::formatQuantity($state)),

                TextColumn::make('impacto_estoque')
                    ->label('Impacto')
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
                    }),

                TextColumn::make('saldo_atual')
                    ->label('Saldo')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => static::formatQuantity($state)),

                TextColumn::make('documento_referencia')
                    ->label('Documento')
                    ->limit(28)
                    ->placeholder('-'),

                TextColumn::make('responsavel_nome')
                    ->label('Responsavel')
                    ->formatStateUsing(fn (?string $state, ProdutoMovimentacao $record): string => $state ?: ($record->user?->name ?? '-')),
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
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->slideOver(),
                DeleteAction::make()->label('Excluir'),
            ]);
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
}
