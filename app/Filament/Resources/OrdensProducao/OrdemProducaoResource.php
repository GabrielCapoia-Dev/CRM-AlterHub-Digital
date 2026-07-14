<?php

namespace App\Filament\Resources\OrdensProducao;

use App\Enum\StatusOrdemProducao;
use App\Filament\Resources\OrdensProducao\Pages\ManageOrdensProducao;
use App\Models\OrdemProducao;
use App\Models\Produto;
use App\Services\Producao\OrdemProducaoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OrdemProducaoResource extends Resource
{
    protected static ?string $model = OrdemProducao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Ordens de producao';

    protected static ?string $modelLabel = 'Ordem de producao';

    protected static ?string $pluralModelLabel = 'Ordens de producao';

    public static ?string $slug = 'producao/ordens';

    protected static string|UnitEnum|null $navigationGroup = 'Producao';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Planejamento da ordem')
                ->description('A ficha tecnica do produto sera copiada como snapshot ao criar a ordem.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('produto_id')
                        ->label('Produto fabricado')
                        ->options(fn (): array => Produto::query()
                            ->where('classificacao', 'fabricado')
                            ->orderBy('nome')
                            ->get()
                            ->mapWithKeys(fn (Produto $produto): array => [
                                $produto->id => trim(($produto->codigo_interno ? $produto->codigo_interno.' - ' : '').$produto->nome),
                            ])->all())
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('quantidade_planejada')
                        ->label('Quantidade planejada')
                        ->numeric()
                        ->step('0.0001')
                        ->minValue(0.0001)
                        ->required(),

                    DatePicker::make('prevista_para')
                        ->label('Previsao de conclusao')
                        ->minDate(now()->toDateString()),

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
            ->columns([
                TextColumn::make('codigo')
                    ->label('Ordem')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('produto_nome_snapshot')
                    ->label('Produto')
                    ->description(fn (OrdemProducao $record): ?string => $record->produto_codigo_snapshot)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StatusOrdemProducao|string $state): string => $state instanceof StatusOrdemProducao
                        ? $state->label()
                        : (StatusOrdemProducao::tryFrom($state)?->label() ?? $state))
                    ->color(fn (StatusOrdemProducao|string $state): string => $state instanceof StatusOrdemProducao
                        ? $state->color()
                        : (StatusOrdemProducao::tryFrom($state)?->color() ?? 'gray')),

                TextColumn::make('quantidade_planejada')
                    ->label('Planejado')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(fn (OrdemProducao $record): string => ' '.($record->unidade_snapshot ?? ''))
                    ->sortable(),

                TextColumn::make('quantidade_produzida')
                    ->label('Produzido')
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(),

                TextColumn::make('custo_total_planejado_snapshot')
                    ->label('Custo planejado')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('prevista_para')
                    ->label('Previsao')
                    ->date('d/m/Y')
                    ->placeholder('Nao informada')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusOrdemProducao::options()),

                SelectFilter::make('produto_id')
                    ->label('Produto')
                    ->relationship('produto', 'nome')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('reservar')
                    ->label('Reservar insumos')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (OrdemProducao $record): bool => $record->podeReservar())
                    ->authorize(fn (OrdemProducao $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (OrdemProducao $record): void {
                        app(OrdemProducaoService::class)->reservarInsumos($record);
                        Notification::make()->title('Insumos reservados')->success()->send();
                    }),

                Action::make('liberar')
                    ->label('Liberar reserva')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (OrdemProducao $record): bool => $record->podeLiberarReserva())
                    ->authorize(fn (OrdemProducao $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (OrdemProducao $record): void {
                        app(OrdemProducaoService::class)->liberarInsumos($record);
                        Notification::make()->title('Reserva liberada')->success()->send();
                    }),

                Action::make('concluir')
                    ->label('Concluir e dar entrada')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('O consumo dos insumos reservados e a entrada do produto acabado serao gravados atomicamente.')
                    ->visible(fn (OrdemProducao $record): bool => $record->podeConcluir())
                    ->authorize(fn (OrdemProducao $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (OrdemProducao $record): void {
                        app(OrdemProducaoService::class)->darEntradaProdutoAcabado($record, null, auth()->user());
                        Notification::make()->title('Produto acabado recebido no estoque')->success()->send();
                    }),

                Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OrdemProducao $record): bool => in_array($record->status, [
                        StatusOrdemProducao::Planejada,
                        StatusOrdemProducao::Reservada,
                    ], true))
                    ->authorize(fn (OrdemProducao $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->action(function (OrdemProducao $record): void {
                        app(OrdemProducaoService::class)->cancelar($record);
                        Notification::make()->title('Ordem cancelada')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrdensProducao::route('/'),
        ];
    }
}
