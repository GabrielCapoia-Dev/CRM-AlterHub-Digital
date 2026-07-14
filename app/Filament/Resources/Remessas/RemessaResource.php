<?php

namespace App\Filament\Resources\Remessas;

use App\Enum\RemessaStatus;
use App\Filament\Resources\Remessas\Pages\ManageRemessas;
use App\Models\Remessa;
use App\Models\RemessaItem;
use App\Services\Operacao\RemessaService;
use App\Services\Operacao\VendaOperacaoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class RemessaResource extends Resource
{
    protected static ?string $model = Remessa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Remessas';

    protected static ?string $modelLabel = 'Remessa';

    protected static ?string $pluralModelLabel = 'Remessas';

    protected static string|UnitEnum|null $navigationGroup = 'Logistica';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with(['pedido', 'transportadora', 'itens.produto']);

        if (! app(VendaOperacaoService::class)->podeVerTodasVendas($user)) {
            $query->whereHas('pedido', fn (Builder $builder) => $builder->where('user_id', $user?->id));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Remessa')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('pedido.codigo')->label('Venda')->searchable()->sortable(),
                TextColumn::make('pedido.cliente_nome_snapshot')->label('Cliente')->searchable()->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RemessaStatus|string $state): string => RemessaStatus::options()[$state instanceof RemessaStatus ? $state->value : $state] ?? (string) $state)
                    ->color(fn (RemessaStatus|string $state): string => match ($state instanceof RemessaStatus ? $state : RemessaStatus::from($state)) {
                        RemessaStatus::Rascunho => 'gray',
                        RemessaStatus::EmSeparacao => 'warning',
                        RemessaStatus::Pronta => 'info',
                        RemessaStatus::Despachada => 'primary',
                        RemessaStatus::Entregue => 'success',
                        RemessaStatus::Cancelada => 'danger',
                    }),
                TextColumn::make('transportadora.razao_social')->label('Transportadora')->placeholder('Retirada'),
                TextColumn::make('itens_sum_quantidade')->sum('itens', 'quantidade')->label('Quantidade'),
                TextColumn::make('valor_frete_custo')->label('Custo frete')->money('BRL'),
                TextColumn::make('valor_frete_cobrado')->label('Frete cobrado')->money('BRL'),
                TextColumn::make('despachada_em')->label('Despachada em')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(RemessaStatus::options()),
            ])
            ->recordActions([
                Action::make('separar')
                    ->label('Iniciar separacao')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Remessa $record): bool => $record->status === RemessaStatus::Rascunho)
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Remessa $record): void {
                        app(RemessaService::class)->iniciarSeparacao($record, auth()->user());
                        Notification::make()->title('Separacao iniciada')->success()->send();
                    }),
                Action::make('pronta')
                    ->label('Marcar pronta')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Remessa $record): bool => $record->status === RemessaStatus::EmSeparacao)
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Remessa $record): void {
                        app(RemessaService::class)->marcarPronta($record, auth()->user());
                        Notification::make()->title('Remessa pronta')->success()->send();
                    }),
                Action::make('despachar')
                    ->label('Despachar')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar baixa fisica do estoque')
                    ->modalDescription(fn (Remessa $record): string => collect(app(RemessaService::class)->impactoEstoque($record))
                        ->map(fn (array $item): string => "{$item['produto']}: -{$item['quantidade_a_baixar']}")
                        ->implode(' | '))
                    ->visible(fn (Remessa $record): bool => $record->status === RemessaStatus::Pronta)
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Remessa $record): void {
                        app(RemessaService::class)->despachar(
                            $record,
                            auth()->user(),
                            "despachar:remessa:{$record->id}",
                        );
                        Notification::make()->title('Remessa despachada e estoque baixado')->success()->send();
                    }),
                Action::make('entregar')
                    ->label('Confirmar entrega')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Remessa $record): bool => $record->status === RemessaStatus::Despachada)
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Remessa $record): void {
                        app(RemessaService::class)->entregar($record, auth()->user());
                        Notification::make()->title('Entrega registrada')->success()->send();
                    }),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->color('danger')
                    ->schema([
                        Textarea::make('justificativa')->required()->minLength(10),
                    ])
                    ->requiresConfirmation()
                    ->visible(fn (Remessa $record): bool => ! in_array($record->status, [RemessaStatus::Despachada, RemessaStatus::Entregue, RemessaStatus::Cancelada], true))
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Remessa $record, array $data): void {
                        app(RemessaService::class)->cancelar($record, auth()->user(), $data['justificativa']);
                        Notification::make()->title('Remessa cancelada')->success()->send();
                    }),
                Action::make('devolver')
                    ->label('Registrar devolucao')
                    ->color('warning')
                    ->visible(fn (Remessa $record): bool => in_array($record->status, [RemessaStatus::Despachada, RemessaStatus::Entregue], true))
                    ->authorize(fn (Remessa $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        Textarea::make('motivo')->label('Motivo')->required()->minLength(10),
                        Repeater::make('itens')
                            ->label('Itens recebidos')
                            ->minItems(1)
                            ->schema([
                                Select::make('remessa_item_id')
                                    ->label('Produto')
                                    ->options(fn (Remessa $record): array => $record->itens()
                                        ->with('produto')
                                        ->get()
                                        ->filter(fn (RemessaItem $item): bool => (float) $item->quantidade > (float) $item->quantidade_devolvida)
                                        ->mapWithKeys(fn (RemessaItem $item): array => [
                                            $item->id => sprintf('%s (saldo %s)', $item->produto?->nome, (float) $item->quantidade - (float) $item->quantidade_devolvida),
                                        ])->all())
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required(),
                                TextInput::make('quantidade')->numeric()->minValue(0.0001)->required(),
                            ])->columns(2),
                    ])
                    ->action(function (Remessa $record, array $data): void {
                        $devolucao = app(RemessaService::class)->devolver(
                            $record->pedido,
                            $data['itens'] ?? [],
                            auth()->user(),
                            $data['motivo'],
                            'devolver:'.Str::uuid(),
                        );
                        Notification::make()->title('Devolucao '.$devolucao->codigo.' registrada')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRemessas::route('/')];
    }
}
