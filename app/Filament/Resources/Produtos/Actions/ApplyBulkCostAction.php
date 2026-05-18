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
                Section::make('Precos de referencia')
                    ->description('Atualize o preco base e o preco minimo apenas quando fizer sentido para todos os itens selecionados.')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->columns(2)
                    ->schema([
                        Toggle::make('apply_preco_tabela')
                            ->label('Atualizar preco base')
                            ->inline(false)
                            ->live(),

                        Toggle::make('apply_preco_minimo')
                            ->label('Atualizar preco minimo')
                            ->inline(false)
                            ->live(),

                        TextInput::make('preco_tabela')
                            ->label('Preco base')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->prefix('R$')
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_preco_tabela'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_preco_tabela')),

                        TextInput::make('preco_minimo')
                            ->label('Preco minimo')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->prefix('R$')
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_preco_minimo'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_preco_minimo')),
                    ]),

                Section::make('Componentes de custo')
                    ->description('Substitua a composicao de impostos, encargos e custos fixos por uma configuracao padrao.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Toggle::make('apply_componentes_custo')
                            ->label('Substituir componentes de custo')
                            ->helperText('Os componentes abaixo serao aplicados para todos os produtos selecionados.')
                            ->inline(false)
                            ->live(),

                        Repeater::make('produtoComponentesCusto')
                            ->label('')
                            ->default(fn (): array => app(ProdutoPricingCalculator::class)->defaultComponentes())
                            ->visible(fn (Get $get): bool => (bool) $get('apply_componentes_custo'))
                            ->columns(5)
                            ->table([
                                TableColumn::make('Nome')->markAsRequired(),
                                TableColumn::make('Categoria')->markAsRequired(),
                                TableColumn::make('Tipo')->markAsRequired(),
                                TableColumn::make('Valor')->markAsRequired(),
                                TableColumn::make('Obrig.'),
                            ])
                            ->schema([
                                TextInput::make('nome')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(255),

                                Select::make('categoria')
                                    ->hiddenLabel()
                                    ->options(ProdutoComponenteCusto::categoriaOptions())
                                    ->required(),

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
                                    ->hiddenLabel(),
                            ])
                            ->addActionLabel('Adicionar componente')
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
