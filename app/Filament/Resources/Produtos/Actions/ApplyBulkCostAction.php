<?php

namespace App\Filament\Resources\Produtos\Actions;

use App\Enum\PermissoesEnum;
use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Services\Produtos\ProdutoCostingService;
use App\Services\Produtos\ProdutoPricingCalculator;
use App\Support\Ui\NumericFormat;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ApplyBulkCostAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('applyBulkCosts')
            ->label('Aplicar custos')
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value) ?? false)
            ->modalWidth('6xl')
            ->modalHeading('Aplicar custos em lote')
            ->modalDescription('Selecione quais campos devem sobrescrever os produtos selecionados.')
            ->modalSubmitActionLabel('Aplicar configuracao')
            ->schema([
                Section::make('Lucro')
                    ->description('Atualize o percentual usado para formar o preco de venda final.')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->schema([
                        Toggle::make('apply_lucro_percentual')
                            ->label('Atualizar percentual de lucro')
                            ->inline(false)
                            ->live(),

                        TextInput::make('lucro_percentual')
                            ->label('Percentual de lucro')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->suffix('%')
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_lucro_percentual'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_lucro_percentual')),
                    ]),

                Section::make('Fatores')
                    ->description('Substitua os fatores dos produtos selecionados por uma configuracao padrao.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Toggle::make('apply_componentes_custo')
                            ->label('Substituir fatores')
                            ->helperText('Os fatores abaixo serao aplicados para todos os produtos selecionados.')
                            ->inline(false)
                            ->live(),

                        Repeater::make('produtoComponentesCusto')
                            ->label('')
                            ->default(fn (): array => app(ProdutoPricingCalculator::class)->defaultComponentes())
                            ->visible(fn (Get $get): bool => (bool) $get('apply_componentes_custo'))
                            ->table([
                                TableColumn::make('Nome do fator')->markAsRequired(),
                                TableColumn::make('Tipo')->markAsRequired(),
                                TableColumn::make('Valor')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('nome')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(255),

                                Select::make('tipo')
                                    ->hiddenLabel()
                                    ->options(ProdutoComponenteCusto::tipoOptions())
                                    ->required(),

                                TextInput::make('valor')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->rule('decimal:0,2')
                                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                    ->placeholder('0,00')
                                    ->required(),

                                Toggle::make('obrigatorio')
                                    ->hidden()
                                    ->default(false),
                            ])
                            ->addActionLabel('Adicionar fator')
                            ->reorderableWithDragAndDrop(false)
                            ->reorderableWithButtons(),
                    ]),
            ])
            ->action(function (Collection $records, array $data): void {
                $records->each(fn (Produto $record) => Gate::authorize('update', $record));

                $updatedCount = app(ProdutoCostingService::class)->applyBulkCostConfiguration($records, $data);

                Notification::make()
                    ->title('Custos aplicados com sucesso')
                    ->body("{$updatedCount} produto(s) foram atualizados.")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
