<?php

namespace App\Services\Produtos;

use App\Models\Produto;
use App\Models\ProdutoComponenteCusto;
use App\Models\Produtos\Insumo;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ProdutoPricingCalculator
{
    public function defaultComponentes(): array
    {
        return [
            $this->makeDefaultComponent('COFINS', 'impostos', 'percentual_sobre_venda', 0, true),
            $this->makeDefaultComponent('PIS', 'impostos', 'percentual_sobre_venda', 0, true),
            $this->makeDefaultComponent('CSLL', 'impostos', 'percentual_sobre_venda', 0, false),
            $this->makeDefaultComponent('IR', 'impostos', 'percentual_sobre_venda', 0, false),
            $this->makeDefaultComponent('IPI', 'impostos', 'percentual_sobre_venda', 0, false),
            $this->makeDefaultComponent('ICMS', 'impostos', 'percentual_sobre_venda', 0, true),
            $this->makeDefaultComponent('Comissão', 'comerciais', 'percentual_sobre_venda', 0, false),
            $this->makeDefaultComponent('Margem', 'comerciais', 'percentual_sobre_venda', 0, true, true),
            $this->makeDefaultComponent('Frete', 'custos_fixos', 'valor_fixo_brl', 0, false),
            $this->makeDefaultComponent('Outras despesas', 'custos_fixos', 'valor_fixo_brl', 0, false),
            $this->makeDefaultComponent('Despesas gerais', 'custos_fixos', 'valor_fixo_brl', 0, false),
        ];
    }

    public function prepareForPersistence(array $data, bool $refreshSnapshots = true): array
    {
        $normalized = $this->normalizePayload($data, $refreshSnapshots);
        $summary = $this->summarize($normalized['produtoInsumos'], $normalized['produtoComponentesCusto']);

        $normalized['custo_base_formacao'] = $summary['custo_base_formacao'];
        $normalized['preco_sugerido'] = $summary['preco_sugerido'];
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

        $custosMap = Insumo::query()
            ->whereIn('id', $insumoIds)
            ->pluck('custo_referencia', 'id');

        return $items
            ->map(function (array $item, int $index) use ($custosMap): array {
                $insumoId = Arr::get($item, 'insumo_id');
                $quantidade = $this->toNullableFloat(Arr::get($item, 'quantidade')) ?? 0.0;
                $custoUnitario = $insumoId
                    ? (float) ($custosMap[$insumoId] ?? 0)
                    : ($this->toNullableFloat(Arr::get($item, 'custo_unitario_snapshot')) ?? 0.0);

                return [
                    ...$item,
                    'ordem' => Arr::get($item, 'ordem', $index),
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
                    'categoria' => $item['categoria'] ?? 'personalizado',
                    'tipo' => $item['tipo'] ?? 'percentual_sobre_venda',
                    'valor' => $this->toNullableFloat($item['valor']) ?? 0.0,
                    'obrigatorio' => filter_var($item['obrigatorio'] ?? false, FILTER_VALIDATE_BOOL),
                    'is_margem' => filter_var($item['is_margem'] ?? false, FILTER_VALIDATE_BOOL),
                    'ordem' => $item['ordem'] ?? $index,
                ];
            });

        if ($componentes->isEmpty()) {
            $componentes = collect($this->defaultComponentes());
        }

        $insumos = collect(Arr::get($data, 'produtoInsumos', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $item, int $index): array {
                return [
                    'id' => $item['id'] ?? null,
                    'insumo_id' => Arr::get($item, 'insumo_id'),
                    'quantidade' => $this->toNullableFloat(Arr::get($item, 'quantidade')) ?? 0.0,
                    'unidade_consumo' => Arr::get($item, 'unidade_consumo'),
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
            'status' => $data['status'] ?? 'em_registro',
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

        $custosAdicionais = round(
            $componentesCollection
                ->filter(fn (array $item): bool => ($item['tipo'] ?? null) === 'valor_fixo_brl')
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $percentualSobreVenda = round(
            $componentesCollection
                ->filter(function (array $item): bool {
                    return ($item['tipo'] ?? null) === 'percentual_sobre_venda'
                        && ! ($item['is_margem'] ?? false);
                })
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $margem = round(
            $componentesCollection
                ->filter(fn (array $item): bool => (bool) ($item['is_margem'] ?? false))
                ->sum(fn (array $item): float => (float) ($item['valor'] ?? 0)),
            4,
        );

        $percentualTotal = round($percentualSobreVenda + $margem, 4);
        $custoBaseFormacao = round($custoTotalInsumos + $custosAdicionais, 4);

        return [
            'custo_total_insumos' => $custoTotalInsumos,
            'custos_adicionais' => $custosAdicionais,
            'percentual_sobre_venda' => $percentualSobreVenda,
            'margem' => $margem,
            'percentual_total' => $percentualTotal,
            'custo_base_formacao' => $custoBaseFormacao,
            'preco_sugerido' => $percentualTotal >= 100
                ? null
                : round($custoBaseFormacao / (1 - ($percentualTotal / 100)), 2),
        ];
    }

    protected function validate(array $data, array $summary): void
    {
        $messages = [];

        if (! in_array($data['status'], array_keys(Produto::statusOptions()), true)) {
            $messages['status'] = 'Selecione um status válido para o produto.';
        }

        if (($data['preco_tabela'] ?? 0) <= 0) {
            $messages['preco_tabela'] = 'Informe um preço base maior que zero.';
        }

        if (($data['preco_minimo'] ?? 0) <= 0) {
            $messages['preco_minimo'] = 'Informe um preço mínimo maior que zero.';
        }

        if (
            ($data['preco_tabela'] ?? null) !== null
            && ($data['preco_minimo'] ?? null) !== null
            && $data['preco_minimo'] > $data['preco_tabela']
        ) {
            $messages['preco_minimo'] = 'O preço mínimo não pode ser maior que o preço base.';
        }

        if ($summary['percentual_total'] >= 100) {
            $messages['produtoComponentesCusto'] = 'A soma dos percentuais e da margem deve ser menor que 100%.';
        }

        foreach ($data['produtoInsumos'] as $index => $item) {
            if (blank($item['insumo_id'] ?? null)) {
                $messages["produtoInsumos.$index.insumo_id"] = 'Selecione um insumo para cada linha da composição.';
            }

            if (($item['quantidade'] ?? 0) <= 0) {
                $messages["produtoInsumos.$index.quantidade"] = 'A quantidade do insumo deve ser maior que zero.';
            }
        }

        foreach ($data['produtoComponentesCusto'] as $index => $item) {
            if (blank($item['nome'] ?? null)) {
                $messages["produtoComponentesCusto.$index.nome"] = 'Nomeie cada componente de custo.';
            }

            if (! array_key_exists($item['categoria'] ?? '', ProdutoComponenteCusto::categoriaOptions())) {
                $messages["produtoComponentesCusto.$index.categoria"] = 'Selecione uma categoria válida para o componente.';
            }

            if (! array_key_exists($item['tipo'] ?? '', ProdutoComponenteCusto::tipoOptions())) {
                $messages["produtoComponentesCusto.$index.tipo"] = 'Selecione um tipo válido para o componente.';
            }

            if (($item['valor'] ?? 0) < 0) {
                $messages["produtoComponentesCusto.$index.valor"] = 'O valor do componente não pode ser negativo.';
            }

            if (($item['is_margem'] ?? false) && ($item['tipo'] ?? null) !== 'percentual_sobre_venda') {
                $messages["produtoComponentesCusto.$index.tipo"] = 'Componentes marcados como margem precisam ser percentuais.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function makeDefaultComponent(
        string $nome,
        string $categoria,
        string $tipo,
        float $valor,
        bool $obrigatorio,
        bool $isMargem = false,
    ): array {
        return [
            'nome' => $nome,
            'categoria' => $categoria,
            'tipo' => $tipo,
            'valor' => $valor,
            'obrigatorio' => $obrigatorio,
            'is_margem' => $isMargem,
        ];
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }
}
