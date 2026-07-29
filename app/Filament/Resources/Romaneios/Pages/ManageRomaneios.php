<?php

namespace App\Filament\Resources\Romaneios\Pages;

use App\Filament\Resources\Romaneios\RomaneioResource;
use App\Models\Acesso\User;
use App\Models\Romaneio;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\RomaneioService;
use App\Support\Ui\NumericFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

class ManageRomaneios extends ManageRecords
{
    protected static string $resource = RomaneioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarRomaneio')
                ->label('Gerar romaneio')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', Romaneio::class) ?? false)
                ->modalHeading('Selecionar pedidos para o romaneio')
                ->modalDescription('Selecione os pedidos e informe aqui a quantidade de volumes de cada um. Os volumes são registrados somente no romaneio.')
                ->modalWidth('6xl')
                ->modalSubmitActionLabel('Gerar romaneio com os pedidos selecionados')
                ->schema([
                    DatePicker::make('data_de')
                        ->label('Data inicial')
                        ->live()
                        ->columnSpan(1),

                    DatePicker::make('data_ate')
                        ->label('Data final')
                        ->live()
                        ->columnSpan(1),

                    Select::make('vendedor_id')
                        ->label('Vendedor')
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->columnSpan(1),

                    CheckboxList::make('pedido_ids')
                        ->label('Pedidos elegiveis')
                        ->options(fn (Get $get): array => static::pedidoOptions($get))
                        ->searchable()
                        ->bulkToggleable()
                        ->required()
                        ->minItems(1)
                        ->columns(1)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, Get $get, mixed $state) => static::syncPedidosVolumes($set, $get, $state))
                        ->helperText('Use a busca para localizar pelo numero do pedido ou nome do cliente.')
                        ->columnSpanFull(),

                    Repeater::make('pedidos_volumes')
                        ->label('Volumes por pedido')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->table([
                            TableColumn::make('Pedido'),
                            TableColumn::make('Quantidade de volumes')->markAsRequired()->width('14rem'),
                        ])
                        ->schema([
                            Hidden::make('pedido_id'),
                            Placeholder::make('pedido_resumo')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => (string) $get('pedido_resumo')),
                            TextInput::make('quantidade_volumes')
                                ->hiddenLabel()
                                ->integer()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->visible(fn (Get $get): bool => count((array) $get('pedido_ids')) > 0)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'oa-romaneio-volumes-table']),

                    Placeholder::make('quantidade_selecionada')
                        ->label('Confirmacao')
                        ->content(fn (Get $get): string => sprintf(
                            'Gerar romaneio com %d pedido(s) selecionado(s).',
                            count((array) $get('pedido_ids')),
                        ))
                        ->columnSpanFull(),

                    Textarea::make('observacao')
                        ->label('Observacoes da carga')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    try {
                        $romaneio = app(RomaneioService::class)->criar(
                            array_map('intval', $data['pedido_ids'] ?? []),
                            auth()->user(),
                            $data['observacao'] ?? null,
                            collect($data['pedidos_volumes'] ?? [])
                                ->mapWithKeys(fn (array $pedido): array => [
                                    (int) ($pedido['pedido_id'] ?? 0) => (int) ($pedido['quantidade_volumes'] ?? 0),
                                ])
                                ->all(),
                        );

                        Notification::make()
                            ->title('Romaneio '.$romaneio->codigo.' gerado')
                            ->body($romaneio->total_pedidos.' pedido(s), '.$romaneio->quantidade_volumes_total.' volume(s).')
                            ->success()
                            ->send();

                        $this->redirect(route('documentos.romaneios.pdf', [
                            'romaneio' => $romaneio,
                            'download' => 1,
                        ]));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Nao foi possivel gerar o romaneio')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        throw $exception;
                    }
                }),
        ];
    }

    protected static function pedidoOptions(Get $get): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $query = app(RomaneioService::class)
            ->elegiveisQuery($user)
            ->with(['vendasOperacao', 'cliente']);

        if ($dataDe = $get('data_de')) {
            $query->whereDate('data_venda', '>=', $dataDe);
        }

        if ($dataAte = $get('data_ate')) {
            $query->whereDate('data_venda', '<=', $dataAte);
        }

        if ($vendedorId = $get('vendedor_id')) {
            $query->where('user_id', $vendedorId);
        }

        return $query
            ->orderByDesc('data_venda')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (VendaOperacaoPedido $pedido): array => [
                $pedido->id => sprintf(
                    '%s - %s | %s | %s | %s kg',
                    $pedido->codigo,
                    $pedido->cliente_nome_snapshot ?: 'Cliente nao identificado',
                    $pedido->data_venda?->format('d/m/Y') ?? '-',
                    NumericFormat::money((float) $pedido->receita_bruta_total),
                    number_format($pedido->pesoTotalKg(), 4, ',', '.'),
                ),
            ])
            ->all();
    }

    protected static function syncPedidosVolumes(Set $set, Get $get, mixed $state): void
    {
        $ids = collect((array) $state)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $atuais = collect((array) $get('pedidos_volumes'))
            ->keyBy(fn (array $pedido): int => (int) ($pedido['pedido_id'] ?? 0));

        if ($ids->isEmpty()) {
            $set('pedidos_volumes', []);

            return;
        }

        $pedidos = VendaOperacaoPedido::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $set('pedidos_volumes', $ids
            ->map(function (int $id) use ($atuais, $pedidos): array {
                $pedido = $pedidos->get($id);
                $atual = $atuais->get($id, []);

                return [
                    'pedido_id' => $id,
                    'pedido_resumo' => $pedido
                        ? sprintf('%s — %s', $pedido->codigo, $pedido->cliente_nome_snapshot ?: 'Cliente não identificado')
                        : 'Pedido #'.$id,
                    'quantidade_volumes' => $atual['quantidade_volumes'] ?? null,
                ];
            })
            ->all());
    }
}
