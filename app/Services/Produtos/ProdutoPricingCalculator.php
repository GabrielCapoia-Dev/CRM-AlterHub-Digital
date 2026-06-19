<?php

namespace App\Services\Produtos;

use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\Produtos\Insumo;
use App\Support\Ui\NumericFormat;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ProdutoPricingCalculator
{
    public const SINGLE_PRODUCT_COST_COMPONENT = 'Custo do produto';

    public const PROFIT_COMPONENT = 'Lucro desejado';

    public function defaultComponentes(): array
    {
        return [];
    }

    public function prepareForPersistence(array $data, bool $refreshSnapshots = true): array
    {
        $normalized = $this->normalizePayload($data, $refreshSnapshots);
        $summary = $this->summarize($normalized['produtoInsumos'], $normalized['produtoComponentesCusto']);

        $normalized['custo_base_formacao'] = $summary['custo_base_formacao'];
        $normalized['preco_sugerido'] = $summary['preco_sugerido'];
        $normalized['preco_minimo'] = $summary['preco_minimo'];
        $normalized['preco_tabela'] = $summary['preco_tabela'];
        $normalized['ativo'] = ! in_array($normalized['status'], ['inativo', 'descontinuado'], true);

        $this->validate($normalized, $summary);

        return $normalized;
    }

    public function summarizeState(array $state, bool $refreshSnapshots = true): array
    {
        $normalized = $this->normalizePayload($state, $refreshSnapshots);

        return $this->summarize($normalized['produtoInsumos'], $normalized['produtoComponentesCusto']);
    }

    public function refreshInsumoSnapshots(array $items): array
    {
        $items = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        $insumoIds = $items
            ->pluck('insumo_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $insumosMap = Insumo::query()
            ->with('tipoUnidadeMedida')
            ->whereIn('id', $insumoIds)
            ->get()
            ->keyBy('id');

        return $items
            ->map(function (array $item, int $index) use ($insumosMap): array {
                $insumoId = Arr::get($item, 'insumo_id');
                $quantidade = $this->toNullableFloat(Arr::get($item, 'quantidade')) ?? 0.0;

                /** @var Insumo|null $insumo */
                $insumo = $insumoId ? $insumosMap->get((int) $insumoId) : null;

                $custoUnitario = $insumo
                    ? (float) $insumo->custo_referencia
                    : ($this->toNullableFloat(Arr::get($item, 'custo_unitario_snapshot')) ?? 0.0);

                return [
                    ...$item,
                    'ordem' => Arr::get($item, 'ordem', $index),
                    'unidade_consumo' => $insumo
                        ? $this->resolveInsumoUnit($insumo)
                        : $this->normalizeString(Arr::get($item, 'unidade_consumo')),
                    'custo_unitario_snapshot' => round($custoUnitario, 4),
                    'custo_total_snapshot' => round($quantidade * $custoUnitario, 4),
                ];
            })
            ->all();
    }

    protected function normalizePayload(array $data, bool $refreshSnapshots): array
    {
        $componentes = collect(Arr::get($data, 'produtoComponentesCusto', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $item, int $index): array {
                return [
                    'id' => $item['id'] ?? null,
                    'nome' => trim((string) ($item['nome'] ?? '')),
                    'categoria' => $item['categoria'] ?? 'fator',
                    'tipo' => $this->normalizeFactorType($item['tipo'] ?? null),
                    'valor' => $this->toNullableFloat($item['valor']) ?? 0.0,
                    'obrigatorio' => filter_var($item['obrigatorio'] ?? false, FILTER_VALIDATE_BOOL),
                    'ordem' => $item['ordem'] ?? $index,
                ];
            });

        $insumos = collect(Arr::get($data, 'produtoInsumos', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $item, int $index): array {
                return [
                    'id' => $item['id'] ?? null,
                    'insumo_id' => Arr::get($item, 'insumo_id'),
                    'quantidade' => $this->toNullableFloat(Arr::get($item, 'quantidade')) ?? 0.0,
                    'unidade_consumo' => $this->normalizeString(Arr::get($item, 'unidade_consumo')),
                    'ordem' => Arr::get($item, 'ordem', $index),
                    'custo_unitario_snapshot' => $this->toNullableFloat(Arr::get($item, 'custo_unitario_snapshot')) ?? 0.0,
                    'custo_total_snapshot' => $this->toNullableFloat(Arr::get($item, 'custo_total_snapshot')) ?? 0.0,
                ];
            });

        if ($refreshSnapshots) {
            $insumos = collect($this->refreshInsumoSnapshots($insumos->all()));
        }

        return [
            ...$data,
            'status' => $data['status'] ?? 'ativo',
            'preco_tabela' => $this->toNullableFloat($data['preco_tabela'] ?? null),
            'preco_minimo' => $this->toNullableFloat($data['preco_minimo'] ?? null),
            'produtoInsumos' => $insumos->all(),
            'produtoComponentesCusto' => $componentes->all(),
        ];
    }

    protected function summarize(array $insumos, array $componentes): array
    {
        $insumosCollection = collect($insumos);
        $componentesCollection = collect($componentes);

        $custoTotalInsumos = round(
            $insumosCollection->sum(fn (array $item): float => (float) ($item['custo_total_snapshot'] ?? 0)),
            4,
        );

        $custoProdutoUnico = round(
            $componentesCollection
                ->filter(fn (array $item): bool => ($item['tipo'] ?? null) === 'valor_fixo_brl')
                ->filter(fn (array $item): bool => ($item['categoria'] ?? null) === 'custo_produto'
                    || ($item['nome'] ?? null) === self::SINGLE_PRODUCT_COST_COMPONENT)
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $fatores = $componentesCollection
            ->reject(fn (array $item): bool => in_array(($item['nome'] ?? ''), [
                self::SINGLE_PRODUCT_COST_COMPONENT,
                self::PROFIT_COMPONENT,
            ], true))
            ->reject(fn (array $item): bool => in_array(($item['categoria'] ?? null), ['custo_produto', 'lucro'], true));

        $fatorDivisor = $fatores
            ->filter(fn (array $item): bool => $this->isDivisorPercentualFactor($item))
            ->reduce(
                fn (float $acumulado, array $item): float => $acumulado * (float) ($item['valor'] ?? 1),
                1.0,
            );

        $somaFixosBrl = round(
            $fatores
                ->filter(fn (array $item): bool => ($item['tipo'] ?? null) === 'valor_fixo_brl')
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $somaPercentuais = round(
            $fatores
                ->filter(fn (array $item): bool => $this->isAdditionalPercentualFactor($item))
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $percentualLucro = round(
            $componentesCollection
                ->filter(fn (array $item): bool => ($item['tipo'] ?? null) === 'percentual')
                ->filter(fn (array $item): bool => ($item['categoria'] ?? null) === 'lucro'
                    || ($item['nome'] ?? null) === self::PROFIT_COMPONENT)
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $precoProduto = round($custoTotalInsumos + $custoProdutoUnico, 4);
        $valorFinalProduto = round((($precoProduto + $somaFixosBrl) / $fatorDivisor) * (1 + ($somaPercentuais / 100)), 4);
        $precoVendaFinal = round($valorFinalProduto * (1 + ($percentualLucro / 100)), 2);

        return [
            'custo_total_insumos' => $custoTotalInsumos,
            'custo_produto_unico' => $custoProdutoUnico,
            'custo_insumos_ou_produto' => $precoProduto,
            'preco_produto' => $precoProduto,
            'soma_fixos_brl' => $somaFixosBrl,
            'soma_percentuais' => $somaPercentuais,
            'fator_divisor' => round($fatorDivisor, 6),
            'valor_final_produto' => $valorFinalProduto,
            'preco_minimo' => round($valorFinalProduto, 2),
            'percentual_lucro' => $percentualLucro,
            'preco_venda_final' => $precoVendaFinal,
            'preco_tabela' => $precoVendaFinal,
            'preco_sugerido' => $precoVendaFinal,
            'custo_base_formacao' => $precoProduto,
        ];
    }

    protected function validate(array $data, array $summary): void
    {
        $messages = [];

        if (! in_array($data['status'], array_keys(Produto::statusOptions()), true)) {
            $messages['status'] = 'Selecione um status valido para o produto.';
        }

        if (($summary['preco_produto'] ?? 0) <= 0) {
            $messages['produtoInsumos'] = 'Informe insumos ou o preco do produto para formar o custo.';
        }

        foreach ($data['produtoInsumos'] as $index => $item) {
            if (blank($item['insumo_id'] ?? null)) {
                $messages["produtoInsumos.$index.insumo_id"] = 'Selecione um insumo para cada linha da composicao.';
            }

            if (($item['quantidade'] ?? 0) <= 0) {
                $messages["produtoInsumos.$index.quantidade"] = 'A quantidade do insumo deve ser maior que zero.';
            }
        }

        foreach ($data['produtoComponentesCusto'] as $index => $item) {
            if (blank($item['nome'] ?? null)) {
                $messages["produtoComponentesCusto.$index.nome"] = 'Nomeie cada fator de custo.';
            }

            if (! array_key_exists($item['tipo'] ?? '', ProdutoComponenteCusto::tipoOptions())) {
                $messages["produtoComponentesCusto.$index.tipo"] = 'Selecione um tipo valido para o fator.';
            }

            if (($item['valor'] ?? 0) < 0) {
                $messages["produtoComponentesCusto.$index.valor"] = 'O valor do fator nao pode ser negativo.';
            }

            if (($item['nome'] ?? '') === self::SINGLE_PRODUCT_COST_COMPONENT && ($item['valor'] ?? 0) <= 0) {
                $messages["produtoComponentesCusto.$index.valor"] = 'Informe o preco do produto.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function normalizeFactorType(?string $type): ?string
    {
        return $type === 'percentual_sobre_venda' ? 'percentual' : $type;
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        return NumericFormat::parse($value, 4);
    }

    protected function resolveInsumoUnit(Insumo $insumo): ?string
    {
        return $insumo->tipoUnidadeMedida?->sigla
            ?: $insumo->tipoUnidadeMedida?->nome;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function isDivisorPercentualFactor(array $fator): bool
    {
        if (($fator['tipo'] ?? null) !== 'percentual') {
            return false;
        }

        $valor = (float) ($fator['valor'] ?? 0);

        return $valor > 0 && $valor < 1;
    }

    protected function isAdditionalPercentualFactor(array $fator): bool
    {
        if (($fator['tipo'] ?? null) !== 'percentual') {
            return false;
        }

        return (float) ($fator['valor'] ?? 0) >= 1;
    }
}
