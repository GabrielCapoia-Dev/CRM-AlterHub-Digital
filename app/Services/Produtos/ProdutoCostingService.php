<?php

namespace App\Services\Produtos;

use App\Enum\ProdutoClassificacao;
use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\ProdutoInsumo;
use App\Support\Ui\NumericFormat;
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

    public function formData(Produto $produto, bool $hideGuidedComponents = true): array
    {
        $componentes = $produto->produtoComponentesCusto()
            ->orderBy('ordem')
            ->get()
            ->map(fn (ProdutoComponenteCusto $item): array => [
                'id' => $item->id,
                'nome' => $item->nome,
                'categoria' => $this->normalizeComponentCategory($item->categoria),
                'tipo' => $this->normalizeComponentType($item->tipo),
                'valor' => (float) $item->valor,
                'obrigatorio' => $item->obrigatorio,
                'ordem' => $item->ordem,
            ])
            ->values()
            ->all();
        $insumos = $produto->produtoInsumos()
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
            ->all();

        return [
            ...$produto->attributesToArray(),
            'status' => $produto->status === 'em_registro' ? 'ativo' : $produto->status,
            'produto_unico_sem_insumo' => $insumos === [],
            'custo_produto_unico' => $this->componentValue($componentes, ProdutoPricingCalculator::SINGLE_PRODUCT_COST_COMPONENT),
            'lucro_percentual' => $this->componentValue($componentes, ProdutoPricingCalculator::PROFIT_COMPONENT),
            'produtoInsumos' => $insumos,
            'produtoComponentesCusto' => $hideGuidedComponents ? $this->visibleComponentes($componentes) : $componentes,
        ];
    }

    public function applyBulkCostConfiguration(EloquentCollection|Collection $produtos, array $data): int
    {
        $applyLucro = filter_var($data['apply_lucro_percentual'] ?? false, FILTER_VALIDATE_BOOL);
        $applyComponentes = filter_var($data['apply_componentes_custo'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $applyLucro && ! $applyComponentes) {
            throw ValidationException::withMessages([
                'apply_lucro_percentual' => 'Selecione pelo menos um bloco de custo para aplicar em lote.',
            ]);
        }

        if ($applyComponentes && empty($data['produtoComponentesCusto'] ?? [])) {
            throw ValidationException::withMessages([
                'produtoComponentesCusto' => 'Informe ao menos um componente de custo para substituir a configuracao atual.',
            ]);
        }

        $updatedCount = 0;

        DB::transaction(function () use ($produtos, $data, $applyLucro, $applyComponentes, &$updatedCount): void {
            foreach ($produtos as $produto) {
                $payload = $this->formData($produto, hideGuidedComponents: false);

                if ($applyLucro) {
                    $payload['lucro_percentual'] = $data['lucro_percentual'] ?? 0;
                    $payload['produtoComponentesCusto'] = $this->replaceProfitComponent(
                        $payload['produtoComponentesCusto'] ?? [],
                        NumericFormat::parse($data['lucro_percentual'] ?? 0, 4) ?? 0.0,
                    );
                }

                if ($applyComponentes) {
                    $payload['produtoComponentesCusto'] = $this->mergeGuidedComponents(
                        $payload['produtoComponentesCusto'] ?? [],
                        $data['produtoComponentesCusto'] ?? [],
                    );
                }

                $prepared = $this->calculator->prepareForPersistence($payload);

                $produto->fill(Arr::except($prepared, [
                    'produtoInsumos',
                    'produtoComponentesCusto',
                    'produto_unico_sem_insumo',
                    'custo_produto_unico',
                    'lucro_percentual',
                ]));
                $produto->save();

                $this->syncProdutoInsumos($produto, $prepared['produtoInsumos']);
                $this->syncProdutoComponentesCusto($produto, $prepared['produtoComponentesCusto']);

                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    public function savePreparedProduct(Produto $produto, array $prepared): Produto
    {
        return DB::transaction(function () use ($produto, $prepared): Produto {
            $insumos = collect($prepared['produtoInsumos'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['insumo_id'] ?? null));
            $classificacaoPreparada = $prepared['classificacao'] ?? ProdutoClassificacao::Revenda;
            $classificacao = $classificacaoPreparada instanceof ProdutoClassificacao
                ? $classificacaoPreparada->value
                : $classificacaoPreparada;

            if ($insumos->isEmpty() && $classificacao === ProdutoClassificacao::Fabricado->value) {
                throw ValidationException::withMessages([
                    'produtoInsumos' => 'Produto fabricado exige uma composicao valida de insumos.',
                ]);
            }

            foreach ($insumos as $index => $item) {
                if ((float) ($item['quantidade'] ?? 0) <= 0) {
                    throw ValidationException::withMessages([
                        "produtoInsumos.{$index}.quantidade" => 'A quantidade da BOM deve ser maior que zero na unidade-base do insumo.',
                    ]);
                }
            }

            $prepared['classificacao'] = $insumos->isNotEmpty()
                ? ProdutoClassificacao::Fabricado->value
                : ProdutoClassificacao::Revenda->value;

            $produto->fill(Arr::except($prepared, [
                'produtoInsumos',
                'produtoComponentesCusto',
                'produto_unico_sem_insumo',
                'custo_produto_unico',
                'lucro_percentual',
            ]));

            $produto->save();

            $this->syncPreparedRelations($produto, $prepared);

            return $produto->refresh();
        });
    }

    public function syncPreparedRelations(Produto $produto, array $prepared, bool $syncInsumos = true): void
    {
        if ($syncInsumos) {
            $this->syncProdutoInsumos($produto, $prepared['produtoInsumos'] ?? []);
        } elseif (($prepared['produtoInsumos'] ?? null) === []) {
            $produto->produtoInsumos()->delete();
        }

        $this->syncProdutoComponentesCusto($produto, $prepared['produtoComponentesCusto'] ?? []);
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
                'categoria' => $item['categoria'] ?? 'fator',
                'tipo' => $item['tipo'] ?? 'percentual',
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

    protected function componentValue(array $componentes, string $nome): float
    {
        $component = collect($componentes)->first(
            fn (array $item): bool => strcasecmp((string) ($item['nome'] ?? ''), $nome) === 0,
        );

        return $component ? (float) ($component['valor'] ?? 0) : 0.0;
    }

    protected function visibleComponentes(array $componentes): array
    {
        return collect($componentes)
            ->reject(fn (array $item): bool => in_array($item['nome'] ?? '', [
                ProdutoPricingCalculator::SINGLE_PRODUCT_COST_COMPONENT,
                ProdutoPricingCalculator::PROFIT_COMPONENT,
            ], true))
            ->values()
            ->all();
    }

    protected function mergeGuidedComponents(array $currentComponentes, array $newFactors): array
    {
        $guided = collect($currentComponentes)
            ->filter(fn (array $item): bool => in_array($item['nome'] ?? '', [
                ProdutoPricingCalculator::SINGLE_PRODUCT_COST_COMPONENT,
                ProdutoPricingCalculator::PROFIT_COMPONENT,
            ], true))
            ->values();

        return collect($newFactors)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->merge($guided)
            ->values()
            ->all();
    }

    protected function replaceProfitComponent(array $componentes, float $lucroPercentual): array
    {
        $items = collect($componentes)
            ->reject(fn (array $item): bool => ($item['nome'] ?? '') === ProdutoPricingCalculator::PROFIT_COMPONENT)
            ->values();

        if ($lucroPercentual > 0) {
            $items->push([
                'nome' => ProdutoPricingCalculator::PROFIT_COMPONENT,
                'categoria' => 'lucro',
                'tipo' => 'percentual',
                'valor' => $lucroPercentual,
                'obrigatorio' => false,
            ]);
        }

        return $items->values()->all();
    }

    protected function normalizeComponentCategory(?string $category): string
    {
        return in_array($category, ['custo_produto', 'lucro'], true) ? $category : 'fator';
    }

    protected function normalizeComponentType(?string $type): string
    {
        return $type === 'valor_fixo_brl' ? 'valor_fixo_brl' : 'percentual';
    }
}
