<?php

namespace App\Services\Produtos;

use App\Models\Produtos\Insumo;
use App\Models\Produtos\InsumoFatorCusto;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InsumoCostingService
{
    public function __construct(
        protected InsumoCostCalculator $calculator,
    ) {
    }

    public function formData(Insumo $insumo): array
    {
        return [
            ...$insumo->attributesToArray(),
            'insumoFatoresCusto' => $insumo->insumoFatoresCusto()
                ->orderBy('ordem')
                ->get()
                ->map(fn (InsumoFatorCusto $item): array => [
                    'id' => $item->id,
                    'nome' => $item->nome,
                    'tipo' => $item->tipo,
                    'valor' => (float) $item->valor,
                    'ordem' => $item->ordem,
                ])
                ->values()
                ->all(),
        ];
    }

    public function applyBulkCostConfiguration(EloquentCollection|Collection $insumos, array $data): int
    {
        $applyFornecedor = filter_var($data['apply_fornecedor_id'] ?? false, FILTER_VALIDATE_BOOL);
        $applyCostContext = filter_var($data['apply_cost_context'] ?? false, FILTER_VALIDATE_BOOL);
        $applyFactors = filter_var($data['apply_fatores_custo'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $applyFornecedor && ! $applyCostContext && ! $applyFactors) {
            throw ValidationException::withMessages([
                'apply_fornecedor_id' => 'Selecione pelo menos um bloco de custo para aplicar em lote.',
            ]);
        }

        if ($applyFactors && empty($data['insumoFatoresCusto'] ?? [])) {
            throw ValidationException::withMessages([
                'insumoFatoresCusto' => 'Informe ao menos um fator de custo para substituir a configuracao atual.',
            ]);
        }

        $updatedCount = 0;

        DB::transaction(function () use ($insumos, $data, $applyFornecedor, $applyCostContext, $applyFactors, &$updatedCount): void {
            foreach ($insumos as $insumo) {
                $payload = $this->formData($insumo);

                if ($applyFornecedor) {
                    $payload['fornecedor_id'] = $data['fornecedor_id'] ?? null;
                }

                if ($applyCostContext) {
                    $payload['origem'] = $data['origem'] ?? $payload['origem'] ?? 'nacional';
                    $payload['moeda_origem'] = $data['moeda_origem'] ?? null;
                    $payload['custo_referencia'] = $data['custo_referencia'] ?? null;
                    $payload['custo_moeda_origem'] = $data['custo_moeda_origem'] ?? null;
                    $payload['taxa_cambio'] = $data['taxa_cambio'] ?? null;
                }

                $finalOrigin = $payload['origem'] ?? 'nacional';

                if ($applyFactors) {
                    if ($finalOrigin !== 'importado') {
                        $label = $insumo->codigo_interno ?: $insumo->nome;

                        throw ValidationException::withMessages([
                            'insumoFatoresCusto' => "O insumo {$label} so pode receber fatores de custo quando a origem final for importado.",
                        ]);
                    }

                    $payload['insumoFatoresCusto'] = $data['insumoFatoresCusto'] ?? [];
                }

                $prepared = $this->calculator->prepareForPersistence($payload);

                $insumo->fill(Arr::except($prepared, ['insumoFatoresCusto']));
                $insumo->save();

                $this->syncFatoresCusto($insumo, $prepared['insumoFatoresCusto']);

                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    protected function syncFatoresCusto(Insumo $insumo, array $items): void
    {
        $existingRecords = $insumo->insumoFatoresCusto()->get()->keyBy('id');
        $keptIds = [];

        foreach (collect($items)->filter(fn (mixed $item): bool => is_array($item))->values() as $index => $item) {
            $payload = [
                'nome' => $item['nome'] ?? null,
                'tipo' => $item['tipo'] ?? null,
                'valor' => $item['valor'] ?? 0,
                'ordem' => $item['ordem'] ?? $index,
            ];

            $record = filled($item['id'] ?? null) ? $existingRecords->get((int) $item['id']) : null;

            if ($record) {
                $record->fill($payload)->save();
                $keptIds[] = $record->id;

                continue;
            }

            $created = $insumo->insumoFatoresCusto()->create($payload);
            $keptIds[] = $created->id;
        }

        $idsToDelete = $existingRecords->keys()->diff($keptIds);

        if ($idsToDelete->isNotEmpty()) {
            $insumo->insumoFatoresCusto()->whereKey($idsToDelete->all())->delete();
        }
    }
}
