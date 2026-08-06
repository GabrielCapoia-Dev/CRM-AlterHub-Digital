<?php

namespace App\Filament\Resources\PedidosSeparacao\Actions;

use App\Models\Acesso\User;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Documentos\VendaFotoService;
use App\Services\Documentos\VendaSeparacaoService;
use App\Support\Ui\LivewireTemporaryUploadResolver;
use App\Support\Ui\NumericFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SepararPedidoAction
{
    public static function make(): Action
    {
        return Action::make('separarPedido')
            ->label(fn (VendaOperacaoPedido $record): string => $record->isSeparado()
                ? 'Revisar separação'
                : 'Separar pedido')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('manageLots', $record) ?? false)
            ->modalHeading(fn (VendaOperacaoPedido $record): string => 'Separação do pedido '.$record->codigo)
            ->modalDescription('Informe os lotes e confirme cada lote com ao menos uma foto. Ao salvar, o pedido ficará pronto para o romaneio.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Concluir separação')
            ->modalCancelActionLabel('Cancelar')
            ->modalFooterActionsAlignment(Alignment::End)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes([
                'class' => 'oa-record-modal oa-separation-modal',
            ])
            ->schema([
                Repeater::make('itens')
                    ->label('Produtos')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->extraAttributes(['class' => 'oa-separation-products'])
                    ->itemLabel(fn (array $state): string => (string) ($state['produto_nome'] ?? 'Produto'))
                    ->schema([
                        Hidden::make('venda_operacao_id'),
                        Hidden::make('produto_nome'),
                        Placeholder::make('produto_resumo')
                            ->label('Quantidade confirmada')
                            ->content(fn (Get $get): string => sprintf(
                                '%s %s',
                                NumericFormat::decimal((float) ($get('quantidade_confirmada') ?? 0)),
                                $get('unidade') ?: 'UN',
                            ))
                            ->extraAttributes(['class' => 'oa-separation-summary'])
                            ->columnSpan(['default' => 12, 'md' => 7]),
                        Hidden::make('quantidade_confirmada'),
                        Hidden::make('unidade'),
                        Placeholder::make('peso_resumo')
                            ->label('Peso unitário')
                            ->content(fn (Get $get): string => (float) ($get('peso_unitario_kg') ?? 0) > 0
                                ? number_format((float) $get('peso_unitario_kg'), 4, ',', '.').' kg'
                                : 'Pendente — cadastre o peso antes de concluir.')
                            ->extraAttributes(fn (Get $get): array => [
                                'class' => (float) ($get('peso_unitario_kg') ?? 0) > 0
                                    ? 'oa-separation-summary'
                                    : 'oa-separation-summary oa-separation-summary--warning',
                            ])
                            ->columnSpan(['default' => 12, 'md' => 5]),
                        Hidden::make('peso_unitario_kg'),
                        Placeholder::make('fotos_existentes')
                            ->label('Fotos já registradas')
                            ->content(fn (Get $get): string => sprintf(
                                '%d foto(s) vinculada(s) a este produto.',
                                (int) ($get('fotos_count') ?? 0),
                            ))
                            ->columnSpanFull(),
                        Hidden::make('fotos_count'),

                        Repeater::make('lotes')
                            ->label('Lotes e fotos')
                            ->addActionLabel('Adicionar lote')
                            ->compact()
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'oa-separation-lots'])
                            ->table([
                                TableColumn::make('Lote')->markAsRequired()->width('20%'),
                                TableColumn::make('Quantidade')->markAsRequired()->width('14%'),
                                TableColumn::make('Validade')->width('18%'),
                                TableColumn::make('Data de fabricação')->width('19%'),
                                TableColumn::make('Imagem')->width('22%'),
                            ])
                            ->schema([
                                TextInput::make('numero_lote')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('quantidade')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->rule('decimal:0,4')
                                    ->minValue(0.0001)
                                    ->required(),
                                DatePicker::make('data_validade')->hiddenLabel(),
                                DatePicker::make('data_fabricacao')->hiddenLabel(),
                                Hidden::make('ano_fabricacao'),
                                Hidden::make('fotos_count'),
                                FileUpload::make('arquivos')
                                    ->hiddenLabel()
                                    ->multiple()
                                    ->storeFiles(false)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(10240)
                                    ->maxFiles(5)
                                    ->image()
                                    ->helperText(fn (Get $get): string => sprintf(
                                        '%d foto(s) já confirmada(s) neste lote.',
                                        (int) ($get('fotos_count') ?? 0),
                                    ))
                                    ->panelLayout('compact')
                                    ->imagePreviewHeight('56')
                                    ->visible(fn (VendaOperacaoPedido $record): bool => auth()->user()?->can('addPhotos', $record) ?? false),
                            ]),
                    ]),

                Textarea::make('observacao')
                    ->label('Observação final do pedido')
                    ->helperText('Uma única observação geral para toda a separação.')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'oa-separation-order-note']),
            ])
            ->fillForm(function (VendaOperacaoPedido $record): array {
                $record->load([
                    'vendasOperacao.produto',
                    'vendasOperacao.lotes.fotos',
                    'vendasOperacao.fotos',
                ]);

                return [
                    'observacao' => $record->separacao_observacao,
                    'itens' => $record->vendasOperacao->map(fn (VendaOperacao $item): array => [
                        'venda_operacao_id' => $item->id,
                        'produto_nome' => $item->produto_nome_snapshot ?: $item->produto?->nome,
                        'quantidade_confirmada' => (float) $item->quantidade,
                        'unidade' => $item->unidade_snapshot,
                        'peso_unitario_kg' => (float) ($item->peso_unitario_kg_snapshot ?: $item->produto?->peso_unitario_kg),
                        'fotos_count' => $item->fotos->count(),
                        'lotes' => ($item->lotes->isEmpty() ? collect([null]) : $item->lotes)->map(fn ($lote): array => [
                            'arquivos' => [],
                            'fotos_count' => $lote?->fotos?->count() ?? 0,
                            'numero_lote' => $lote?->numero_lote,
                            'quantidade' => $lote ? (float) $lote->quantidade : null,
                            'ano_fabricacao' => $lote?->ano_fabricacao,
                            'data_fabricacao' => $lote?->data_fabricacao?->toDateString(),
                            'data_validade' => $lote?->data_validade?->toDateString(),
                        ])->all(),
                    ])->all(),
                ];
            })
            ->action(function (VendaOperacaoPedido $record, array $data): void {
                Gate::authorize('manageLots', $record);
                /** @var User $actor */
                $actor = auth()->user();
                $uploadsPendentes = [];

                try {
                    $separacao = app(VendaSeparacaoService::class);
                    $fotos = app(VendaFotoService::class);

                    foreach ($data['itens'] ?? [] as $itemData) {
                        foreach (array_values($itemData['lotes'] ?? []) as $loteData) {
                            foreach (LivewireTemporaryUploadResolver::resolve($loteData['arquivos'] ?? []) as $arquivo) {
                                $arquivoPendente = $fotos->armazenarUploadPendente(
                                    $record,
                                    $arquivo,
                                    $actor,
                                );
                                $uploadsPendentes[] = [
                                    ...$arquivoPendente,
                                    'venda_operacao_id' => (int) $itemData['venda_operacao_id'],
                                    'numero_lote' => trim((string) ($loteData['numero_lote'] ?? '')),
                                ];
                            }
                        }
                    }

                    DB::transaction(function () use (
                        $record,
                        $data,
                        $actor,
                        $separacao,
                        $fotos,
                        $uploadsPendentes,
                    ): void {
                        $separacao->atualizarObservacaoPedido(
                            $record,
                            $data['observacao'] ?? null,
                            $actor,
                        );

                        foreach ($data['itens'] ?? [] as $itemData) {
                            $item = $record->vendasOperacao()->findOrFail($itemData['venda_operacao_id']);
                            $item = $separacao->registrarLotes(
                                $item,
                                array_values($itemData['lotes'] ?? []),
                                $actor,
                            );

                            foreach ($uploadsPendentes as $upload) {
                                if ((int) $upload['venda_operacao_id'] !== (int) $item->id) {
                                    continue;
                                }

                                $numeroLote = mb_strtolower((string) $upload['numero_lote']);
                                $lote = $item->lotes->first(
                                    fn ($registro): bool => mb_strtolower($registro->numero_lote) === $numeroLote,
                                );

                                if (! $lote) {
                                    throw ValidationException::withMessages([
                                        'lotes' => 'Não foi possível vincular a foto ao lote informado.',
                                    ]);
                                }

                                $fotos->registrarArquivoArmazenado(
                                    $record,
                                    $upload['path'],
                                    $upload['nome_original'],
                                    $actor,
                                    $item,
                                    lote: $lote,
                                );
                            }
                        }

                        $separacao->concluirSeparacao($record, $actor);
                    });
                } catch (Throwable $exception) {
                    foreach ($uploadsPendentes as $upload) {
                        Storage::disk('local')->delete($upload['path']);
                    }

                    if ($exception instanceof ValidationException) {
                        Notification::make()
                            ->title('Não foi possível concluir a separação')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();
                    }

                    throw $exception;
                }

                Notification::make()
                    ->title('Pedido separado')
                    ->body('Lotes, fotos e observação foram salvos. O pedido está pronto para entrar em um romaneio.')
                    ->success()
                    ->send();
            });
    }
}
