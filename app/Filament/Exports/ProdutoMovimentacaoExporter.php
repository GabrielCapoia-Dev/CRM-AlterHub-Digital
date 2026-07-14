<?php

namespace App\Filament\Exports;

use App\Models\ProdutoMovimentacao;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

class ProdutoMovimentacaoExporter extends CrmExporter
{
    protected static ?string $model = ProdutoMovimentacao::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('realizado_em')
                ->label('Realizado em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('produto.codigo_interno')->label('Código do produto'),
            ExportColumn::make('produto.nome')->label('Produto'),
            ExportColumn::make('produto.categoriaProduto.nome')->label('Categoria'),
            ExportColumn::make('tipo')
                ->label('Tipo')
                ->formatStateUsing(fn (?string $state): string => ProdutoMovimentacao::tipoOptions()[$state] ?? 'Não definido'),
            ExportColumn::make('origem_tipo')->label('Tipo de origem'),
            ExportColumn::make('origem_id')->label('ID da origem'),
            ExportColumn::make('quantidade')
                ->label('Quantidade')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('impacto_estoque')
                ->label('Impacto no estoque')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('saldo_anterior')
                ->label('Saldo anterior')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('saldo_atual')
                ->label('Saldo atual')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('unidade')->label('Unidade'),
            ExportColumn::make('documento_referencia')->label('Documento / referência'),
            ExportColumn::make('fornecedor.razao_social')->label('Fornecedor'),
            ExportColumn::make('motivo')->label('Motivo'),
            ExportColumn::make('origem_destino')->label('Origem / contexto'),
            ExportColumn::make('destino')->label('Destino'),
            ExportColumn::make('responsavel')
                ->label('Responsável')
                ->state(fn (ProdutoMovimentacao $record): ?string => $record->responsavel_nome ?: $record->user?->name),
            ExportColumn::make('valor_unitario')
                ->label('Valor unitário')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('valor_total')
                ->label('Valor total')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('observacao')->label('Observação'),
            ExportColumn::make('observacao_interna')->label('Observação interna')->enabledByDefault(false),
            ExportColumn::make('estorno_de_id')->label('Estorno da movimentação'),
            ExportColumn::make('estornadoPor.name')->label('Estornado por'),
            ExportColumn::make('estornada_em')
                ->label('Estornada em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
            ExportColumn::make('idempotency_key')->label('Chave de idempotência')->enabledByDefault(false),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return static::scopeAuthorized($query)
            ->with(['produto.categoriaProduto', 'fornecedor', 'user', 'estornadoPor']);
    }

    protected function filePrefix(): string
    {
        $produtoId = $this->options['produto_id'] ?? null;

        return $produtoId
            ? "movimentacoes-produto-{$produtoId}"
            : 'movimentacoes-produtos';
    }

    protected function sheetName(): string
    {
        return 'Movimentações de produtos';
    }

    protected function columnWidths(): array
    {
        return [
            'realizado_em' => 20,
            'produto.codigo_interno' => 19,
            'produto.nome' => 32,
            'produto.categoriaProduto.nome' => 24,
            'tipo' => 18,
            'origem_tipo' => 20,
            'origem_id' => 14,
            'quantidade' => 14,
            'impacto_estoque' => 18,
            'saldo_anterior' => 16,
            'saldo_atual' => 16,
            'unidade' => 12,
            'documento_referencia' => 24,
            'fornecedor.razao_social' => 32,
            'motivo' => 28,
            'origem_destino' => 28,
            'destino' => 28,
            'responsavel' => 26,
            'valor_unitario' => 16,
            'valor_total' => 16,
            'observacao' => 42,
            'observacao_interna' => 42,
            'estorno_de_id' => 22,
            'estornadoPor.name' => 26,
            'estornada_em' => 20,
            'idempotency_key' => 38,
        ];
    }
}
