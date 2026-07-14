<?php

namespace App\Filament\Exports;

use App\Models\Produto;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

class EstoqueAtualExporter extends CrmExporter
{
    protected static ?string $model = Produto::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('codigo_interno')->label('Código interno'),
            ExportColumn::make('nome')->label('Produto'),
            ExportColumn::make('categoriaProduto.nome')->label('Categoria'),
            ExportColumn::make('unidade_medida')->label('Unidade'),
            ExportColumn::make('status')
                ->label('Status do produto')
                ->formatStateUsing(fn (?string $state): string => Produto::statusOptions()[$state] ?? 'Não definido'),
            ExportColumn::make('estoque_atual')
                ->label('Estoque atual')
                ->state(fn (Produto $record): ?float => $record->estoqueAtual()),
            ExportColumn::make('estoque_reservado')
                ->label('Estoque reservado')
                ->formatStateUsing(fn (mixed $state): float => (float) $state),
            ExportColumn::make('estoque_disponivel')
                ->label('Estoque disponível')
                ->state(fn (Produto $record): float => $record->estoqueDisponivel()),
            ExportColumn::make('estoque_minimo')
                ->label('Estoque mínimo')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('situacao_estoque')
                ->label('Situação')
                ->state(function (Produto $record): string {
                    if (! $record->possuiHistoricoEstoque()) {
                        return 'Sem histórico';
                    }

                    return $record->estoqueEstaBaixo() ? 'Estoque baixo' : 'Regular';
                }),
            ExportColumn::make('custo_base_formacao')
                ->label('Custo unitário')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('valor_estoque')
                ->label('Valor em estoque')
                ->state(function (Produto $record): ?float {
                    $estoque = $record->estoqueAtual();

                    if ($estoque === null || $record->custo_base_formacao === null) {
                        return null;
                    }

                    return round($estoque * (float) $record->custo_base_formacao, 4);
                }),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return static::scopeAuthorized($query)
            ->with(['categoriaProduto'])
            ->withCount('produtoMovimentacoes')
            ->withSum('produtoMovimentacoes as estoque_atual', 'impacto_estoque');
    }

    protected function filePrefix(): string
    {
        return 'estoque-atual-produtos';
    }

    protected function sheetName(): string
    {
        return 'Estoque atual';
    }

    protected function columnWidths(): array
    {
        return [
            'codigo_interno' => 18,
            'nome' => 34,
            'categoriaProduto.nome' => 24,
            'unidade_medida' => 12,
            'status' => 18,
            'estoque_atual' => 16,
            'estoque_reservado' => 18,
            'estoque_disponivel' => 18,
            'estoque_minimo' => 16,
            'situacao_estoque' => 18,
            'custo_base_formacao' => 17,
            'valor_estoque' => 18,
        ];
    }
}
