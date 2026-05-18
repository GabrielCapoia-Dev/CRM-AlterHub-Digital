<?php

namespace App\Filament\Resources\Insumos\Actions;

use App\Enum\PermissoesEnum;
use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use App\Services\Produtos\InsumoCostingService;
use App\Support\Ui\NumericFormat;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
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
            ->visible(fn (): bool => auth()->user()?->hasPermissionTo(PermissoesEnum::EditarInsumos->value) ?? false)
            ->modalWidth('6xl')
            ->modalHeading('Aplicar custos em lote')
            ->modalDescription('Atualize fornecedor, origem, cambio e fatores de custo para os insumos selecionados.')
            ->modalSubmitActionLabel('Aplicar configuracao')
            ->schema([
                Section::make('Fornecedor preferencial')
                    ->description('Use este bloco quando o mesmo fornecedor deve ser replicado para todos os insumos selecionados.')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->schema([
                        Toggle::make('apply_fornecedor_id')
                            ->label('Atualizar fornecedor preferencial')
                            ->inline(false)
                            ->live(),

                        Select::make('fornecedor_id')
                            ->label('Fornecedor preferencial')
                            ->options(fn (): array => Fornecedor::query()
                                ->orderBy('razao_social')
                                ->get()
                                ->mapWithKeys(fn (Fornecedor $record): array => [
                                    $record->uuid => filled($record->codigo_interno)
                                        ? "{$record->codigo_interno} - {$record->razao_social}"
                                        : $record->razao_social,
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => (bool) $get('apply_fornecedor_id'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_fornecedor_id')),
                    ]),

                Section::make('Contexto de custo')
                    ->description('Atualize a origem comercial e o custo base que sera usado para recalcular o custo final.')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->schema([
                        Toggle::make('apply_cost_context')
                            ->label('Atualizar origem e custo base')
                            ->inline(false)
                            ->live(),

                        Radio::make('origem')
                            ->label('Origem')
                            ->options(Insumo::origemOptions())
                            ->inline()
                            ->visible(fn (Get $get): bool => (bool) $get('apply_cost_context'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_cost_context'))
                            ->live(),

                        TextInput::make('custo_referencia')
                            ->label('Custo unitario em BRL')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->prefix('R$')
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'nacional'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'nacional')),

                        TextInput::make('custo_moeda_origem')
                            ->label('Custo na moeda de origem')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->placeholder('0,00')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado')),

                        TextInput::make('taxa_cambio')
                            ->label('Taxa de cambio')
                            ->numeric()
                            ->rule('decimal:0,6')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state, 6))
                            ->placeholder('0,000000')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado')),

                        Select::make('moeda_origem')
                            ->label('Moeda de origem')
                            ->options(Insumo::moedaOptions())
                            ->visible(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado'))
                            ->required(fn (Get $get): bool => (bool) $get('apply_cost_context') && ($get('origem') === 'importado')),
                    ]),

                Section::make('Fatores de custo')
                    ->description('Substitua os fatores dos insumos importados pela mesma composicao.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Toggle::make('apply_fatores_custo')
                            ->label('Substituir fatores de custo')
                            ->helperText('Use este bloco apenas para insumos cuja origem final sera importado.')
                            ->inline(false)
                            ->live(),

                        Repeater::make('insumoFatoresCusto')
                            ->label('')
                            ->visible(fn (Get $get): bool => (bool) $get('apply_fatores_custo'))
                            ->table([
                                TableColumn::make('Nome do fator')->markAsRequired(),
                                TableColumn::make('Tipo')->markAsRequired(),
                                TableColumn::make('Valor')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('nome')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ex.: Frete internacional'),

                                Select::make('tipo')
                                    ->hiddenLabel()
                                    ->options(InsumoFatorCusto::tipoOptions())
                                    ->required(),

                                TextInput::make('valor')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->rule('decimal:0,2')
                                    ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                                    ->placeholder('0,00')
                                    ->required(),
                            ])
                            ->addActionLabel('Adicionar fator')
                            ->reorderableWithDragAndDrop(false)
                            ->reorderableWithButtons(),
                    ]),
            ])
            ->action(function (Collection $records, array $data): void {
                $records->each(fn (Insumo $record) => Gate::authorize('update', $record));

                $updatedCount = app(InsumoCostingService::class)->applyBulkCostConfiguration($records, $data);

                Notification::make()
                    ->title('Custos aplicados com sucesso')
                    ->body("{$updatedCount} insumo(s) foram atualizados.")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
