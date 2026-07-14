<?php

namespace App\Filament\Exports\Actions;

use App\Filament\Exports\EstoqueAtualExporter;
use App\Filament\Exports\InsumoMovimentacaoExporter;
use App\Filament\Exports\ProdutoExporter;
use App\Filament\Exports\ProdutoMovimentacaoExporter;
use App\Filament\Exports\VendaOperacaoExporter;
use App\Models\InsumoMovimentacao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\VendaOperacaoPedido;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CrmExportActions
{
    public const DEFAULT_CHUNK_SIZE = 250;

    public static function produtos(?int $chunkSize = null): ExportAction
    {
        return self::make(
            name: 'exportarProdutos',
            exporter: ProdutoExporter::class,
            model: Produto::class,
            configurationKey: 'produtos',
            chunkSize: $chunkSize,
        )->label('Exportar produtos');
    }

    public static function produtoDetalhe(): ExportAction
    {
        return self::make(
            name: 'exportarProdutoDetalhe',
            exporter: ProdutoExporter::class,
            model: Produto::class,
            configurationKey: 'produtos',
            chunkSize: 1,
        )
            ->label('Exportar ficha XLSX')
            ->modifyQueryUsing(
                fn (Builder $query, Produto $record): Builder => $query->whereKey($record->getKey()),
            );
    }

    public static function estoqueAtual(?int $chunkSize = null): ExportAction
    {
        return self::make(
            name: 'exportarEstoqueAtual',
            exporter: EstoqueAtualExporter::class,
            model: Produto::class,
            configurationKey: 'estoque_atual',
            chunkSize: $chunkSize,
        )->label('Exportar estoque');
    }

    public static function produtoMovimentacoes(?int $produtoId = null, ?int $chunkSize = null): ExportAction
    {
        $action = self::make(
            name: 'exportarProdutoMovimentacoes',
            exporter: ProdutoMovimentacaoExporter::class,
            model: ProdutoMovimentacao::class,
            configurationKey: 'produto_movimentacoes',
            chunkSize: $chunkSize,
        );

        if ($produtoId !== null) {
            $action
                ->options(['produto_id' => $produtoId])
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('produto_id', $produtoId),
                );
        }

        return $action->label('Exportar movimentos');
    }

    public static function insumoMovimentacoes(?int $insumoId = null, ?int $chunkSize = null): ExportAction
    {
        $action = self::make(
            name: 'exportarInsumoMovimentacoes',
            exporter: InsumoMovimentacaoExporter::class,
            model: InsumoMovimentacao::class,
            configurationKey: 'insumo_movimentacoes',
            chunkSize: $chunkSize,
        );

        if ($insumoId !== null) {
            $action
                ->options(['insumo_id' => $insumoId])
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('insumo_id', $insumoId),
                );
        }

        return $action->label('Exportar movimentos');
    }

    public static function vendas(?int $chunkSize = null): ExportAction
    {
        return self::make(
            name: 'exportarVendas',
            exporter: VendaOperacaoExporter::class,
            model: VendaOperacaoPedido::class,
            configurationKey: 'vendas',
            chunkSize: $chunkSize,
        )->label('Exportar vendas');
    }

    /**
     * @param  class-string<Exporter>  $exporter
     * @param  class-string<Model>  $model
     */
    private static function make(
        string $name,
        string $exporter,
        string $model,
        string $configurationKey,
        ?int $chunkSize,
    ): ExportAction {
        return ExportAction::make($name)
            ->label('Exportar XLSX')
            ->exporter($exporter)
            ->formats([ExportFormat::Xlsx])
            ->chunkSize(self::resolveChunkSize($configurationKey, $chunkSize))
            ->authorize('viewAny', $model);
    }

    private static function resolveChunkSize(string $key, ?int $chunkSize): int
    {
        $chunkSize ??= (int) config(
            "crm.exports.chunk_sizes.{$key}",
            config('crm.exports.chunk_size', self::DEFAULT_CHUNK_SIZE),
        );

        return max(1, min($chunkSize, 5000));
    }
}
