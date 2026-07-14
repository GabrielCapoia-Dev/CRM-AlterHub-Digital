<?php

namespace App\Filament\Exports;

use App\Models\Produto;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

class ProdutoExporter extends CrmExporter
{
    protected static ?string $model = Produto::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('codigo_interno')->label('Código interno'),
            ExportColumn::make('nome')->label('Produto'),
            ExportColumn::make('categoriaProduto.nome')->label('Categoria'),
            ExportColumn::make('classificacao')
                ->label('Classificação')
                ->state(fn (Produto $record): ?string => $record->classificacao?->label()),
            ExportColumn::make('origem')
                ->label('Origem')
                ->state(fn (Produto $record): ?string => $record->origem?->label()),
            ExportColumn::make('fornecedor.razao_social')->label('Fornecedor'),
            ExportColumn::make('marca')->label('Marca'),
            ExportColumn::make('descricao')->label('Descrição'),
            ExportColumn::make('unidade_medida')->label('Unidade'),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state): string => Produto::statusOptions()[$state] ?? 'Não definido'),
            ExportColumn::make('ncm')->label('NCM'),
            ExportColumn::make('estoque_minimo')
                ->label('Estoque mínimo')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('preco_tabela')
                ->label('Preço de tabela')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('preco_minimo')
                ->label('Preço mínimo')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('custo_base_formacao')
                ->label('Custo base')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('preco_sugerido')
                ->label('Preço sugerido')
                ->formatStateUsing(fn (mixed $state): ?float => $state === null ? null : (float) $state),
            ExportColumn::make('ativo')
                ->label('Ativo')
                ->state(fn (Produto $record): string => $record->ativo ? 'Sim' : 'Não'),
            ExportColumn::make('observacao')->label('Observação')->enabledByDefault(false),
            ExportColumn::make('created_at')
                ->label('Cadastrado em')
                ->formatStateUsing(fn (mixed $state): ?string => $state?->format('d/m/Y H:i')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return static::scopeAuthorized($query)
            ->with(['categoriaProduto', 'fornecedor']);
    }

    protected function filePrefix(): string
    {
        return 'produtos';
    }

    protected function sheetName(): string
    {
        return 'Produtos';
    }

    protected function columnWidths(): array
    {
        return [
            'codigo_interno' => 18,
            'nome' => 32,
            'categoriaProduto.nome' => 24,
            'classificacao' => 16,
            'origem' => 14,
            'fornecedor.razao_social' => 34,
            'marca' => 20,
            'descricao' => 46,
            'unidade_medida' => 12,
            'status' => 16,
            'ncm' => 14,
            'estoque_minimo' => 16,
            'preco_tabela' => 16,
            'preco_minimo' => 16,
            'custo_base_formacao' => 16,
            'preco_sugerido' => 16,
            'ativo' => 10,
            'observacao' => 46,
            'created_at' => 20,
        ];
    }
}
