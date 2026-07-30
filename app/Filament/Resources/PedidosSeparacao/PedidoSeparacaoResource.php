<?php

namespace App\Filament\Resources\PedidosSeparacao;

use App\Enum\SeparacaoStatus;
use App\Enum\VendaStatus;
use App\Filament\Clusters\VendasCluster;
use App\Filament\Resources\PedidosSeparacao\Actions\SepararPedidoAction;
use App\Filament\Resources\PedidosSeparacao\Pages\ManagePedidosSeparacao;
use App\Filament\Resources\Romaneios\RomaneioResource;
use App\Models\Acesso\User;
use App\Models\Romaneio;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\RomaneioService;
use App\Services\Operacao\VendaOperacaoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PedidoSeparacaoResource extends Resource
{
    protected static ?string $model = VendaOperacaoPedido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Separação de pedidos';

    protected static ?string $modelLabel = 'Pedido em separação';

    protected static ?string $pluralModelLabel = 'Pedidos em separação';

    protected static ?string $cluster = VendasCluster::class;

    protected static ?int $navigationSort = 2;

    public static ?string $slug = 'separacao-pedidos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(VendaOperacaoService::class)
            ->queryPorPerfil($user)
            ->where('status', VendaStatus::Confirmada->value)
            ->whereIn('separacao_status', [
                SeparacaoStatus::Aguardando->value,
                SeparacaoStatus::Separado->value,
                SeparacaoStatus::RetornadoRomaneio->value,
            ])
            ->withCount(['vendasOperacao', 'fotos']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Pedido')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('cliente_nome_snapshot')
                    ->label('Cliente')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('data_venda')
                    ->label('Venda em')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('separacao_status')
                    ->label('Status da separação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SeparacaoStatus::tryFrom((string) $state)?->label() ?? 'Aguardando separação')
                    ->color(fn (?string $state): string => SeparacaoStatus::tryFrom((string) $state)?->color() ?? 'warning'),
                TextColumn::make('vendas_operacao_count')
                    ->label('Produtos')
                    ->numeric(),
                TextColumn::make('fotos_count')
                    ->label('Fotos')
                    ->numeric(),
                TextColumn::make('separado_em')
                    ->label('Separado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextColumn::make('retorno_romaneio_descricao')
                    ->label('Retorno do romaneio')
                    ->placeholder('-')
                    ->wrap()
                    ->visible(fn (?VendaOperacaoPedido $record): bool => $record === null
                        || filled($record->retorno_romaneio_descricao)),
            ])
            ->defaultSort('data_venda', 'asc')
            ->searchPlaceholder('Buscar por pedido ou cliente...')
            ->filters([
                SelectFilter::make('separacao_status')
                    ->label('Status')
                    ->options([
                        SeparacaoStatus::Aguardando->value => SeparacaoStatus::Aguardando->label(),
                        SeparacaoStatus::Separado->value => SeparacaoStatus::Separado->label(),
                        SeparacaoStatus::RetornadoRomaneio->value => SeparacaoStatus::RetornadoRomaneio->label(),
                    ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (VendaOperacaoPedido $record): bool => $record->isSeparado()
                && (auth()->user()?->can('create', Romaneio::class) ?? false))
            ->recordActions([
                SepararPedidoAction::make(),
                Action::make('fotos')
                    ->label('Visualizar fotos')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (VendaOperacaoPedido $record): bool => $record->fotos_count > 0
                        && (auth()->user()?->can('viewAttachments', $record) ?? false))
                    ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Fotos do pedido '.$record->codigo)
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (VendaOperacaoPedido $record): View => view('filament.resources.vendas-operacao.modals.fotos', [
                        'pedido' => $record->load(['fotos.item', 'fotos.user']),
                    ])),
            ])
            ->toolbarActions([
                static::generateManifestBulkAction(),
            ]);
    }

    protected static function generateManifestBulkAction(): BulkAction
    {
        return BulkAction::make('gerarRomaneio')
            ->label('Gerar romaneio')
            ->icon('heroicon-o-truck')
            ->color('primary')
            ->modalHeading('Gerar romaneio dos pedidos selecionados')
            ->modalDescription('Informe a quantidade de volumes de cada pedido. O romaneio ficará disponível na tela de Romaneios.')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Gerar romaneio')
            ->fillForm(fn (Collection $records): array => [
                'pedidos_volumes' => $records->map(fn (VendaOperacaoPedido $pedido): array => [
                    'pedido_id' => $pedido->id,
                    'pedido_resumo' => sprintf(
                        '%s — %s',
                        $pedido->codigo,
                        $pedido->cliente_nome_snapshot ?: 'Cliente não identificado',
                    ),
                    'quantidade_volumes' => null,
                ])->values()->all(),
            ])
            ->schema([
                Repeater::make('pedidos_volumes')
                    ->label('Volumes por pedido')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(['default' => 1, 'md' => 3])
                    ->itemLabel(fn (array $state): string => (string) ($state['pedido_resumo'] ?? 'Pedido selecionado'))
                    ->schema([
                        Hidden::make('pedido_id'),
                        TextInput::make('pedido_resumo')
                            ->label('Pedido')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        TextInput::make('quantidade_volumes')
                            ->label('Quantidade de volumes')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->extraAttributes(['class' => 'oa-romaneio-volumes-table']),
                Textarea::make('observacao')
                    ->label('Observações da carga')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (Collection $records, array $data, $livewire): void {
                try {
                    $volumes = collect($data['pedidos_volumes'] ?? [])
                        ->mapWithKeys(fn (array $pedido): array => [
                            (int) ($pedido['pedido_id'] ?? 0) => (int) ($pedido['quantidade_volumes'] ?? 0),
                        ])
                        ->all();
                    $romaneio = app(RomaneioService::class)->criar(
                        $records->modelKeys(),
                        auth()->user(),
                        $data['observacao'] ?? null,
                        $volumes,
                    );

                    Notification::make()
                        ->title('Romaneio '.$romaneio->codigo.' gerado')
                        ->body($romaneio->total_pedidos.' pedido(s) foram enviados para a tela de Romaneios.')
                        ->success()
                        ->send();
                    $livewire->redirect(RomaneioResource::getUrl());
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Não foi possível gerar o romaneio')
                        ->body(collect($exception->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePedidosSeparacao::route('/'),
        ];
    }
}
