<?php

namespace App\Filament\Exports;

use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

class VendaOperacaoExporter extends CrmExporter
{
    protected static ?string $model = VendaOperacaoPedido::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('codigo')->label('Venda'),
            ExportColumn::make('data_venda')
                ->label('Data da venda')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y')),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state): string => VendaOperacaoPedido::statusOptions()[$state] ?? 'Não definido'),
            ExportColumn::make('versao')->label('Versão'),
            ExportColumn::make('cliente_nome_snapshot')->label('Cliente'),
            ExportColumn::make('cliente_documento_snapshot')->label('Documento do cliente'),
            ExportColumn::make('vendedor_nome_snapshot')->label('Vendedor'),
            ExportColumn::make('itens_count')->label('Itens'),
            ExportColumn::make('quantidade_total')
                ->label('Quantidade total')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('receita_bruta_total')
                ->label('Receita bruta')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('receita_liquida_total')
                ->label('Receita líquida')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('custo_total_snapshot')
                ->label('Custo total')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('valor_frete_cobrado')
                ->label('Frete cobrado')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('valor_frete_custo')
                ->label('Custo do frete')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('lucro_bruto_total')
                ->label('Lucro bruto')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('lucro_apos_impostos_total')
                ->label('Lucro após impostos')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('margem_percentual')
                ->label('Margem (%)')
                ->state(function (VendaOperacaoPedido $record): ?float {
                    $receitaLiquida = (float) $record->receita_liquida_total;

                    if ($receitaLiquida === 0.0) {
                        return null;
                    }

                    return round(((float) $record->lucro_apos_impostos_total / $receitaLiquida) * 100, 2);
                }),
            ExportColumn::make('origemPedido.codigo')->label('Venda de origem'),
            ExportColumn::make('aprovadoPor.name')->label('Aprovado por'),
            ExportColumn::make('aprovado_em')
                ->label('Aprovado em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('confirmada_em')
                ->label('Confirmada em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('cancelada_em')
                ->label('Cancelada em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('concluida_em')
                ->label('Concluída em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('observacao')->label('Observação'),
            ExportColumn::make('motivo_recusa')->label('Motivo da recusa'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $query = static::scopeAuthorized($query);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (! app(VendaOperacaoService::class)->podeVerTodasVendas($user)) {
            $query->where('user_id', $user->getKey());
        }

        return $query->with(['origemPedido', 'aprovadoPor']);
    }

    protected function filePrefix(): string
    {
        return 'vendas';
    }

    protected function sheetName(): string
    {
        return 'Vendas';
    }

    protected function columnWidths(): array
    {
        return [
            'codigo' => 16,
            'data_venda' => 16,
            'status' => 22,
            'versao' => 10,
            'cliente_nome_snapshot' => 34,
            'cliente_documento_snapshot' => 22,
            'vendedor_nome_snapshot' => 28,
            'itens_count' => 10,
            'quantidade_total' => 17,
            'receita_bruta_total' => 17,
            'receita_liquida_total' => 17,
            'custo_total_snapshot' => 17,
            'valor_frete_cobrado' => 16,
            'valor_frete_custo' => 16,
            'lucro_bruto_total' => 17,
            'lucro_apos_impostos_total' => 21,
            'margem_percentual' => 15,
            'origemPedido.codigo' => 17,
            'aprovadoPor.name' => 28,
            'aprovado_em' => 20,
            'confirmada_em' => 20,
            'cancelada_em' => 20,
            'concluida_em' => 20,
            'observacao' => 42,
            'motivo_recusa' => 42,
        ];
    }
}
