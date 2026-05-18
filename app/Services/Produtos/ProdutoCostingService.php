<?php

namespace App\Services\Produtos;

use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\ProdutoInsumo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdutoCostingService
{
    public function __construct(
        protected ProdutoPricingCalculator $calculator,
    ) {
    }

    public function formData(Produto $produto): array
    {
        return [
            ...$produto->attributesToArray(),
            'status' => $produto->status === 'em_registro' ? 'ativo' : $produto->status,
            'produtoInsumos' => $produto->produtoInsumos()
                ->orderBy('ordem')
                ->get()
                ->map(fn (ProdutoInsumo $item): array => [
                    'id' => $item->id,
                    'insumo_id' => $item->insumo_id,
                    'quantidade' => (float) $item->quantidade,
                    'unidade_consumo' => $item->unidade_consumo,
                    'ordem' => $item->ordem,
                    'custo_unitario_snapshot' => (float) $item->custo_unitario_snapshot,
                    'custo_total_snapshot' => (float) $item->custo_total_snapshot,
                ])
                ->values()
                ->all(),
            'produtoComponentesCusto' => $produto->produtoComponentesCusto()
                ->orderBy('ordem')
                ->get()
                ->map(fn (ProdutoComponenteCusto $item): array => [
                    'id' => $item->id,
                    'nome' => $item->nome,
                    'categoria' => $item->categoria,
                    'tipo' => $item->tipo,
                    'valor' => (float) $item->valor,
                    'obrigatorio' => $item->obrigatorio,
                    'ordem' => $item->ordem,
                ])
                ->values()
                ->all(),
        ];
    }

    public function applyBulkCostConfiguration(EloquentCollection|Collection $produtos, array $data): int
    {
        $applyPrecoTabela = filter_var($data['apply_preco_tabela'] ?? false, FILTER_VALIDATE_BOOL);
        $applyPrecoMinimo = filter_var($data['apply_preco_minimo'] ?? false, FILTER_VALIDATE_BOOL);
        $applyComponentes = filter_var($data['apply_componentes_custo'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $applyPrecoTabela && ! $applyPrecoMinimo && ! $applyComponentes) {
            throw ValidationException::withMessages([
                'apply_preco_tabela' => 'Selecione pelo menos um bloco de custo para aplicar em lote.',
            ]);
        }

        if ($applyComponentes && empty($data['produtoComponentesCusto'] ?? [])) {
            throw ValidationException::withMessages([
                'produtoComponentesCusto' => 'Informe ao menos um componente de custo para substituir a configuracao atual.',
            ]);
        }

        $updatedCount = 0;

        DB::transaction(function () use ($produtos, $data, $applyPrecoTabela, $applyPrecoMinimo, $applyComponentes, &$updatedCount): void {
            foreach ($produtos as $produto) {
                $payload = $this->formData($produto);

                if ($applyPrecoTabela) {
                    $payload['preco_tabela'] = $data['preco_tabela'] ?? null;
                }

                if ($applyPrecoMinimo) {
                    $payload['preco_minimo'] = $data['preco_minimo'] ?? null;
                }

                if ($applyComponentes) {
                    $payload['produtoComponentesCusto'] = $data['produtoComponentesCusto'] ?? [];
                }

                $prepared = $this->calculator->prepareForPersistence($payload);

                $produto->fill(Arr::except($prepared, ['produtoInsumos', 'produtoComponentesCusto']));
                $produto->save();

                $this->syncProdutoInsumos($produto, $prepared['produtoInsumos']);
                $this->syncProdutoComponentesCusto($produto, $prepared['produtoComponentesCusto']);

                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    protected function syncProdutoInsumos(Produto $produto, array $items): void
    {
        $existingRecords = $produto->produtoInsumos()->get()->keyBy('id');
        $keptIds = [];

        foreach (collect($items)->filter(fn (mixed $item): bool => is_array($item))->values() as $index => $item) {
            $payload = [
                'insumo_id' => $item['insumo_id'] ?? null,
                'quantidade' => $item['quantidade'] ?? 0,
                'unidade_consumo' => $item['unidade_consumo'] ?? null,
                'ordem' => $item['ordem'] ?? $index,
                'custo_unitario_snapshot' => $item['custo_unitario_snapshot'] ?? 0,
                'custo_total_snapshot' => $item['custo_total_snapshot'] ?? 0,
            ];

            $record = filled($item['id'] ?? null) ? $existingRecords->get((int) $item['id']) : null;

            if ($record) {
                $record->fill($payload)->save();
                $keptIds[] = $record->id;

                continue;
            }

            $created = $produto->produtoInsumos()->create($payload);
            $keptIds[] = $created->id;
        }

        $idsToDelete = $existingRecords->keys()->diff($keptIds);

        if ($idsToDelete->isNotEmpty()) {
            $produto->produtoInsumos()->whereKey($idsToDelete->all())->delete();
        }
    }

    protected function syncProdutoComponentesCusto(Produto $produto, array $items): void
    {
        $existingRecords = $produto->produtoComponentesCusto()->get()->keyBy('id');
        $keptIds = [];

        foreach (collect($items)->filter(fn (mixed $item): bool => is_array($item))->values() as $index => $item) {
            $payload = [
                'nome' => $item['nome'] ?? null,
                'categoria' => $item['categoria'] ?? 'personalizado',
                'tipo' => $item['tipo'] ?? 'percentual_sobre_venda',
                'valor' => $item['valor'] ?? 0,
                'obrigatorio' => filter_var($item['obrigatorio'] ?? false, FILTER_VALIDATE_BOOL),
                'ordem' => $item['ordem'] ?? $index,
            ];

            $record = filled($item['id'] ?? null) ? $existingRecords->get((int) $item['id']) : null;

            if ($record) {
                $record->fill($payload)->save();
                $keptIds[] = $record->id;

                continue;
            }

            $created = $produto->produtoComponentesCusto()->create($payload);
            $keptIds[] = $created->id;
        }

        $idsToDelete = $existingRecords->keys()->diff($keptIds);

        if ($idsToDelete->isNotEmpty()) {
            $produto->produtoComponentesCusto()->whereKey($idsToDelete->all())->delete();
        }
    }
}
