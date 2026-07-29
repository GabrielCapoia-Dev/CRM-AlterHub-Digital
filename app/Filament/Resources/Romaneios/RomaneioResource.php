<?php

namespace App\Filament\Resources\Romaneios;

use App\Filament\Resources\Romaneios\Pages\ManageRomaneios;
use App\Models\Romaneio;
use App\Services\Operacao\RomaneioService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class RomaneioResource extends Resource
{
    protected static ?string $model = Romaneio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Romaneios';

    protected static ?string $modelLabel = 'Romaneio';

    protected static ?string $pluralModelLabel = 'Romaneios';

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 4;

    public static ?string $slug = 'estoque/romaneios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Romaneio')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Romaneio::STATUS_CANCELADO ? 'Cancelado' : 'Ativo')
                    ->color(fn (string $state): string => $state === Romaneio::STATUS_CANCELADO ? 'danger' : 'success'),
                TextColumn::make('gerado_em')->label('Gerado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Responsavel')->placeholder('-')->searchable(),
                TextColumn::make('total_pedidos')->label('Pedidos')->numeric(),
                TextColumn::make('quantidade_volumes_total')->label('Volumes')->numeric(),
                TextColumn::make('peso_total_kg')->label('Peso')->formatStateUsing(fn ($state): string => number_format((float) $state, 4, ',', '.').' kg'),
            ])
            ->defaultSort('gerado_em', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Romaneio::STATUS_ATIVO => 'Ativo',
                        Romaneio::STATUS_CANCELADO => 'Cancelado',
                    ]),
            ])
            ->recordActions([
                Action::make('pedidos')
                    ->label('Visualizar pedidos')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading(fn (Romaneio $record): string => 'Pedidos do '.$record->codigo)
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (Romaneio $record): View => view('filament.resources.romaneios.pedidos', [
                        'romaneio' => $record->load(['pedidos.itens', 'user', 'canceladoPor']),
                    ])),

                Action::make('pdf')
                    ->label(fn (Romaneio $record): string => $record->historicos()
                        ->whereIn('evento', ['pdf_gerado', 'pdf_reimpresso'])
                        ->exists()
                            ? 'Gerar PDF novamente'
                            : 'Gerar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Romaneio $record): bool => auth()->user()?->can('print', $record) ?? false)
                    ->url(fn (Romaneio $record): string => route('documentos.romaneios.pdf', [
                        'romaneio' => $record,
                        'download' => 1,
                    ])),

                Action::make('cancelar')
                    ->label('Cancelar romaneio')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Romaneio $record): bool => auth()->user()?->can('cancel', $record) ?? false)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('justificativa')
                            ->label('Justificativa')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000),
                    ])
                    ->action(function (Romaneio $record, array $data): void {
                        try {
                            app(RomaneioService::class)->cancelar(
                                $record,
                                auth()->user(),
                                $data['justificativa'],
                            );
                            Notification::make()
                                ->title('Romaneio cancelado')
                                ->body('Os pedidos foram liberados para um novo romaneio e o historico foi preservado.')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Nao foi possivel cancelar o romaneio')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRomaneios::route('/'),
        ];
    }
}
