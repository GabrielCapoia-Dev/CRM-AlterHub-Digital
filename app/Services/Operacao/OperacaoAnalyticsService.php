<?php

namespace App\Services\Operacao;

use App\Models\Categorias\CategoriaProduto;
use App\Models\DespesaOperacional;
use App\Models\InsumoMovimentacao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\VendaOperacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperacaoAnalyticsService
{
    public function availableYears(): array
    {
        $years = collect([
            ...VendaOperacao::query()->efetivadas()->pluck('ano_referencia')->all(),
            ...DespesaOperacional::query()->pluck('ano_referencia')->all(),
            now()->year,
            now()->year - 1,
            now()->year - 2,
            now()->year - 3,
            now()->year - 4,
        ])
            ->filter()
            ->map(fn (mixed $year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years !== [] ? $years : [now()->year];
    }

    public function produtoOptions(): array
    {
        return Produto::query()
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Produto $produto): array => [
                (string) $produto->id => $produto->codigo_interno
                    ? "{$produto->codigo_interno} - {$produto->nome}"
                    : $produto->nome,
            ])
            ->all();
    }

    public function categoriaProdutoOptions(): array
    {
        return CategoriaProduto::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn (string $nome, int|string $id): array => [(string) $id => $nome])
            ->all();
    }

    public function categoriaDespesaOptions(): array
    {
        return DespesaOperacional::categoriaOptions();
    }

    public function vendedorOptions(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $normalized['vendedor_nome'] = null;

        return $this->vendasQuery($normalized, withRelations: false)
            ->whereNotNull('vendedor_nome')
            ->pluck('vendedor_nome')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $nome): array => [$nome => $nome])
            ->all();
    }

    public function clienteOptions(array $filters = []): array
    {
        $normalized = $this->normalizeFilters($filters);
        $normalized['cliente_nome'] = null;

        return $this->vendasQuery($normalized, withRelations: false)
            ->whereNotNull('cliente_nome')
            ->pluck('cliente_nome')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $nome): array => [$nome => $nome])
            ->all();
    }

    public function currentAverageCostForProduct(Produto $produto): float
    {
        $movimentacoes = $produto->relationLoaded('produtoMovimentacoes')
            ? $produto->produtoMovimentacoes->sortBy([
                ['realizado_em', 'asc'],
                ['id', 'asc'],
            ])->values()
            : $produto->produtoMovimentacoes()
                ->orderBy('realizado_em')
                ->orderBy('id')
                ->get();

        $saldo = 0.0;
        $valorEstoque = 0.0;

        foreach ($movimentacoes as $movimentacao) {
            $tipo = (string) $movimentacao->tipo;
            $quantidade = (float) $movimentacao->quantidade;

            if ($tipo === 'entrada') {
                $valorTotal = $movimentacao->valor_total !== null
                    ? (float) $movimentacao->valor_total
                    : ((float) ($movimentacao->valor_unitario ?? 0)) * $quantidade;

                $saldo += $quantidade;
                $valorEstoque += $valorTotal;

                continue;
            }

            if (in_array($tipo, ['saida', 'consumo_interno', 'perda'], true)) {
                $quantidadeSaida = abs((float) $movimentacao->impacto_estoque) ?: $quantidade;
                $custoMedioAtual = $saldo > 0 ? $valorEstoque / $saldo : 0.0;

                $saldo -= $quantidadeSaida;
                $valorEstoque -= $custoMedioAtual * $quantidadeSaida;

                if ($saldo <= 0.0001) {
                    $saldo = 0.0;
                    $valorEstoque = 0.0;
                }

                continue;
            }

            if ($tipo === 'ajuste') {
                $custoBase = $movimentacao->valor_unitario !== null
                    ? (float) $movimentacao->valor_unitario
                    : ($saldo > 0 ? $valorEstoque / $saldo : (float) ($produto->custo_base_formacao ?? 0));

                $saldo = $quantidade;
                $valorEstoque = $custoBase * $saldo;
            }
        }

        if ($saldo > 0.0001) {
            return round($valorEstoque / $saldo, 4);
        }

        return round((float) ($produto->custo_base_formacao ?? 0), 4);
    }

    public function productStockSnapshot(Produto $produto): array
    {
        return [
            'estoque_atual' => (float) ($produto->estoqueAtual() ?? 0),
            'custo_medio' => $this->currentAverageCostForProduct($produto),
        ];
    }

    public function getVisaoConsolidadaSnapshot(array $filters): array
    {
        $dre = $this->getDreSnapshot($filters);

        if (! $dre['ok']) {
            return $dre;
        }

        $profitRows = $this->getProfitByProductRows($filters);
        $topProduct = collect($profitRows)->sortByDesc('lucro_liquido')->first();

        return [
            'ok' => true,
            'period' => $dre['period'],
            'metrics' => $dre['metrics'],
            'profit_rows' => $profitRows,
            'top_product' => $topProduct,
        ];
    }

    public function getDreSnapshot(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $period = $this->resolvePeriod($normalized);

        if (! $period['ok']) {
            return [
                'ok' => false,
                'error' => $period['error'],
                'period' => $period,
                'metrics' => [],
                'vendas' => [],
                'despesas' => [],
                'cmv_por_produto' => [],
            ];
        }

        $vendas = $this->vendasQuery($normalized)->get();
        $despesas = $this->despesasQuery($normalized)->get();

        $receitaBruta = round((float) $vendas->sum('receita_bruta'), 2);
        $icmsTotal = round((float) $vendas->sum('icms_valor'), 2);
        $outrosImpostosTotal = round((float) $vendas->sum('outros_impostos_valor'), 2);
        $receitaLiquida = round((float) $vendas->sum('receita_liquida'), 2);
        $cmv = round((float) $vendas->sum('custo_total_snapshot'), 2);
        $despesasOperacionais = round((float) $despesas->sum('valor'), 2);
        $lucroBruto = round($receitaLiquida - $cmv, 2);
        $lucroOperacional = round($lucroBruto - $despesasOperacionais, 2);
        $lucroLiquido = $lucroOperacional;
        $margemLiquidaPerc = $receitaLiquida > 0
            ? round(($lucroLiquido / $receitaLiquida) * 100, 2)
            : null;

        $cmvPorProduto = $vendas
            ->groupBy(fn (VendaOperacao $venda): string => (string) ($venda->produto_id ?? 0))
            ->map(function (Collection $group): array {
                /** @var VendaOperacao $first */
                $first = $group->first();

                return [
                    'produto_id' => $first->produto_id,
                    'produto_codigo' => $first->produto_codigo_snapshot,
                    'produto_nome' => $first->produto_nome_snapshot,
                    'qtd' => round((float) $group->sum('quantidade'), 4),
                    'custo_total' => round((float) $group->sum('custo_total_snapshot'), 2),
                    'receita_bruta' => round((float) $group->sum('receita_bruta'), 2),
                ];
            })
            ->sortByDesc('custo_total')
            ->values()
            ->all();

        return [
            'ok' => true,
            'period' => $period,
            'metrics' => [
                'receita_bruta' => $receitaBruta,
                'icms_total' => $icmsTotal,
                'outros_impostos_total' => $outrosImpostosTotal,
                'receita_liquida' => $receitaLiquida,
                'cmv' => $cmv,
                'despesas_operacionais' => $despesasOperacionais,
                'lucro_bruto' => $lucroBruto,
                'lucro_operacional' => $lucroOperacional,
                'lucro_liquido' => $lucroLiquido,
                'margem_liquida_perc' => $margemLiquidaPerc,
                'qtd_vendas' => $vendas->count(),
                'qtd_despesas' => $despesas->count(),
            ],
            'vendas' => $vendas
                ->map(fn (VendaOperacao $venda): array => [
                    'id' => $venda->id,
                    'data' => optional($venda->data_venda)?->toDateString(),
                    'produto_id' => $venda->produto_id,
                    'produto_codigo' => $venda->produto_codigo_snapshot,
                    'produto_nome' => $venda->produto_nome_snapshot,
                    'quantidade' => (float) $venda->quantidade,
                    'preco_unitario' => (float) $venda->preco_unitario,
                    'receita_bruta' => (float) $venda->receita_bruta,
                    'custo_total' => (float) $venda->custo_total_snapshot,
                    'impostos' => (float) $venda->icms_valor + (float) $venda->outros_impostos_valor,
                    'lucro_apos_impostos' => (float) $venda->lucro_apos_impostos,
                    'cliente_nome' => $venda->cliente_nome,
                    'vendedor_nome' => $venda->vendedor_nome,
                ])
                ->all(),
            'despesas' => $despesas
                ->map(fn (DespesaOperacional $despesa): array => [
                    'id' => $despesa->id,
                    'data' => optional($despesa->data_competencia)?->toDateString(),
                    'descricao' => $despesa->descricao,
                    'categoria' => $despesa->categoria,
                    'subcategoria' => $despesa->subcategoria,
                    'tipo' => $despesa->tipo,
                    'valor' => (float) $despesa->valor,
                    'produto_id' => $despesa->produto_id,
                    'produto_nome' => $despesa->produto?->nome,
                ])
                ->all(),
            'cmv_por_produto' => $cmvPorProduto,
        ];
    }

    public function getProfitByProductRows(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $period = $this->resolvePeriod($normalized);

        if (! $period['ok']) {
            return [];
        }

        $vendas = $this->vendasQuery($normalized)->get();
        $despesas = $this->despesasQuery($normalized)
            ->whereNotNull('produto_id')
            ->get()
            ->groupBy(fn (DespesaOperacional $despesa): string => (string) $despesa->produto_id);

        return $vendas
            ->groupBy(fn (VendaOperacao $venda): string => (string) ($venda->produto_id ?? 0))
            ->map(function (Collection $group, string $produtoId) use ($despesas): array {
                /** @var VendaOperacao $first */
                $first = $group->first();
                $despesasAlocadas = round((float) ($despesas->get($produtoId)?->sum('valor') ?? 0), 2);
                $receitaBruta = round((float) $group->sum('receita_bruta'), 2);
                $impostos = round((float) $group->sum(fn (VendaOperacao $venda) => (float) $venda->icms_valor + (float) $venda->outros_impostos_valor), 2);
                $receitaLiquida = round((float) $group->sum('receita_liquida'), 2);
                $cmv = round((float) $group->sum('custo_total_snapshot'), 2);
                $lucroBruto = round($receitaLiquida - $cmv, 2);
                $lucroLiquido = round($lucroBruto - $despesasAlocadas, 2);

                return [
                    'produto_id' => $first->produto_id,
                    'produto_codigo' => $first->produto_codigo_snapshot,
                    'produto_nome' => $first->produto_nome_snapshot,
                    'produto_categoria' => $first->produto_categoria_snapshot,
                    'qtd' => round((float) $group->sum('quantidade'), 4),
                    'receita_bruta' => $receitaBruta,
                    'impostos' => $impostos,
                    'receita_liquida' => $receitaLiquida,
                    'cmv' => $cmv,
                    'lucro_bruto' => $lucroBruto,
                    'despesas_alocadas' => $despesasAlocadas,
                    'lucro_liquido' => $lucroLiquido,
                    'margem_lucro_liquido_perc' => $receitaLiquida > 0
                        ? round(($lucroLiquido / $receitaLiquida) * 100, 2)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    public function getProductDrilldown(array $filters, int $produtoId): array
    {
        $normalized = $this->normalizeFilters($filters);
        $normalized['produto_id'] = $produtoId;

        return [
            'vendas' => $this->vendasQuery($normalized)->get()
                ->map(fn (VendaOperacao $venda): array => [
                    'data' => optional($venda->data_venda)?->toDateString(),
                    'quantidade' => (float) $venda->quantidade,
                    'preco_unitario' => (float) $venda->preco_unitario,
                    'receita_bruta' => (float) $venda->receita_bruta,
                    'custo_total' => (float) $venda->custo_total_snapshot,
                    'impostos' => (float) $venda->icms_valor + (float) $venda->outros_impostos_valor,
                    'lucro_apos_impostos' => (float) $venda->lucro_apos_impostos,
                    'cliente_nome' => $venda->cliente_nome,
                    'vendedor_nome' => $venda->vendedor_nome,
                ])
                ->all(),
            'despesas' => $this->despesasQuery($normalized)->get()
                ->map(fn (DespesaOperacional $despesa): array => [
                    'data' => optional($despesa->data_competencia)?->toDateString(),
                    'descricao' => $despesa->descricao,
                    'categoria' => $despesa->categoria,
                    'subcategoria' => $despesa->subcategoria,
                    'valor' => (float) $despesa->valor,
                ])
                ->all(),
        ];
    }

    public function getDashboardSnapshot(array $filters): array
    {
        $dre = $this->getDreSnapshot($filters);

        if (! $dre['ok']) {
            return [
                'ok' => false,
                'error' => $dre['error'],
                'period' => $dre['period'],
            ];
        }

        $normalized = $this->normalizeFilters($filters);
        $vendas = $this->vendasQuery($normalized)->get();
        $despesas = $this->despesasQuery($normalized)->get();
        $profitRows = collect($this->getProfitByProductRows($normalized));

        $salesByDay = $vendas
            ->groupBy(fn (VendaOperacao $venda): string => optional($venda->data_venda)?->toDateString() ?? now()->toDateString())
            ->map(fn (Collection $group, string $date): array => [
                'label' => Carbon::parse($date)->format('d/m'),
                'receita' => round((float) $group->sum('receita_bruta'), 2),
                'lucro' => round((float) $group->sum('lucro_apos_impostos'), 2),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        $expensesByCategory = $despesas
            ->groupBy('categoria')
            ->map(fn (Collection $group, string $categoria): array => [
                'categoria' => $categoria,
                'label' => DespesaOperacional::categoriaOptions()[$categoria] ?? $categoria,
                'valor' => round((float) $group->sum('valor'), 2),
            ])
            ->sortByDesc('valor')
            ->values()
            ->all();

        $sellerPerformance = $vendas
            ->filter(fn (VendaOperacao $venda): bool => filled($venda->vendedor_nome))
            ->groupBy('vendedor_nome')
            ->map(fn (Collection $group, string $nome): array => [
                'nome' => $nome,
                'receita' => round((float) $group->sum('receita_bruta'), 2),
                'lucro' => round((float) $group->sum('lucro_apos_impostos'), 2),
                'qtd' => $group->count(),
            ])
            ->sortByDesc('receita')
            ->values()
            ->all();

        $clientRanking = $vendas
            ->filter(fn (VendaOperacao $venda): bool => filled($venda->cliente_nome))
            ->groupBy('cliente_nome')
            ->map(fn (Collection $group, string $nome): array => [
                'nome' => $nome,
                'receita' => round((float) $group->sum('receita_bruta'), 2),
                'lucro' => round((float) $group->sum('lucro_apos_impostos'), 2),
                'qtd' => $group->count(),
            ])
            ->sortByDesc('receita')
            ->values()
            ->take(6)
            ->all();

        $lowStockProducts = Produto::query()
            ->withCount('produtoMovimentacoes')
            ->withSum('produtoMovimentacoes', 'impacto_estoque')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Produto $produto): bool => $produto->estoqueEstaBaixo())
            ->map(fn (Produto $produto): array => [
                'produto_id' => $produto->id,
                'codigo' => $produto->codigo_interno,
                'nome' => $produto->nome,
                'estoque_atual' => (float) ($produto->estoqueAtual() ?? 0),
                'estoque_minimo' => (float) ($produto->estoque_minimo ?? 0),
            ])
            ->sortBy('estoque_atual')
            ->values()
            ->take(6)
            ->all();

        $recentMovements = collect(
            ProdutoMovimentacao::query()
                ->with('produto')
                ->orderByDesc('realizado_em')
                ->limit(6)
                ->get()
                ->map(fn (ProdutoMovimentacao $movimentacao): array => [
                    'origem' => 'Produto',
                    'item' => $movimentacao->produto?->nome ?? 'Produto removido',
                    'codigo' => $movimentacao->produto?->codigo_interno,
                    'tipo' => ProdutoMovimentacao::tipoOptions()[$movimentacao->tipo] ?? $movimentacao->tipo,
                    'impacto' => (float) $movimentacao->impacto_estoque,
                    'saldo' => (float) $movimentacao->saldo_atual,
                    'data' => optional($movimentacao->realizado_em)?->toDateTimeString(),
                ])
                ->all()
        )
            ->merge(
                InsumoMovimentacao::query()
                    ->with('insumo')
                    ->orderByDesc('realizado_em')
                    ->limit(6)
                    ->get()
                    ->map(fn (InsumoMovimentacao $movimentacao): array => [
                        'origem' => 'Insumo',
                        'item' => $movimentacao->insumo?->nome ?? 'Insumo removido',
                        'codigo' => $movimentacao->insumo?->codigo_interno,
                        'tipo' => InsumoMovimentacao::tipoOptions()[$movimentacao->tipo] ?? $movimentacao->tipo,
                        'impacto' => (float) $movimentacao->impacto_estoque,
                        'saldo' => (float) $movimentacao->saldo_atual,
                        'data' => optional($movimentacao->realizado_em)?->toDateTimeString(),
                    ])
                    ->all()
            )
            ->sortByDesc('data')
            ->values()
            ->take(8)
            ->all();

        return [
            'ok' => true,
            'period' => $dre['period'],
            'metrics' => $dre['metrics'],
            'sales_by_day' => $salesByDay,
            'expenses_by_category' => $expensesByCategory,
            'top_products' => $profitRows
                ->sortByDesc('receita_bruta')
                ->take(6)
                ->values()
                ->all(),
            'seller_performance' => $sellerPerformance,
            'client_ranking' => $clientRanking,
            'low_stock_products' => $lowStockProducts,
            'recent_movements' => $recentMovements,
        ];
    }

    public function resolvePeriod(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $mode = $normalized['period_mode'];

        if ($mode === 'intervalo') {
            if (! $normalized['date_from'] || ! $normalized['date_to']) {
                return [
                    'ok' => false,
                    'error' => 'Informe um intervalo valido com data inicial e final.',
                ];
            }

            $from = Carbon::parse($normalized['date_from'])->startOfDay();
            $to = Carbon::parse($normalized['date_to'])->endOfDay();

            if ($from->greaterThan($to)) {
                return [
                    'ok' => false,
                    'error' => 'A data inicial precisa ser menor ou igual a data final.',
                ];
            }

            return [
                'ok' => true,
                'mode' => 'intervalo',
                'from' => $from,
                'to' => $to,
                'label' => $from->format('d/m/Y') . ' ate ' . $to->format('d/m/Y'),
            ];
        }

        if (! $normalized['year'] || ! $normalized['month']) {
            return [
                'ok' => false,
                'error' => 'Selecione um mes e um ano validos.',
            ];
        }

        $from = Carbon::create((int) $normalized['year'], (int) $normalized['month'], 1)->startOfMonth();
        $to = (clone $from)->endOfMonth();

        return [
            'ok' => true,
            'mode' => 'mes',
            'from' => $from,
            'to' => $to,
            'label' => $from->translatedFormat('F/Y'),
        ];
    }

    public function normalizeFilters(array $filters): array
    {
        return [
            'period_mode' => ($filters['period_mode'] ?? 'mes') === 'intervalo' ? 'intervalo' : 'mes',
            'month' => filled($filters['month'] ?? null) ? (int) $filters['month'] : null,
            'year' => filled($filters['year'] ?? null) ? (int) $filters['year'] : null,
            'date_from' => $this->normalizeString($filters['date_from'] ?? $filters['dateFrom'] ?? null),
            'date_to' => $this->normalizeString($filters['date_to'] ?? $filters['dateTo'] ?? null),
            'produto_id' => filled($filters['produto_id'] ?? null) ? (int) $filters['produto_id'] : null,
            'categoria_despesa' => $this->normalizeString($filters['categoria_despesa'] ?? null),
            'categoria_produto_id' => filled($filters['categoria_produto_id'] ?? null) ? (int) $filters['categoria_produto_id'] : null,
            'vendedor_nome' => $this->normalizeString($filters['vendedor_nome'] ?? null),
            'cliente_nome' => $this->normalizeString($filters['cliente_nome'] ?? null),
        ];
    }

    protected function vendasQuery(array $filters, bool $withRelations = true): Builder
    {
        $normalized = $this->normalizeFilters($filters);
        $period = $this->resolvePeriod($normalized);
        $query = VendaOperacao::query()->efetivadas();

        if ($withRelations) {
            $query->with(['produto.categoriaProduto', 'user']);
        }

        if (! $period['ok']) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereBetween('data_venda', [
            $period['from']->toDateString(),
            $period['to']->toDateString(),
        ]);

        if ($normalized['produto_id']) {
            $query->where('produto_id', $normalized['produto_id']);
        }

        if ($normalized['categoria_produto_id']) {
            $query->whereHas('produto', fn (Builder $builder) => $builder->where('categoria_produto_id', $normalized['categoria_produto_id']));
        }

        if ($normalized['vendedor_nome']) {
            $query->where('vendedor_nome', $normalized['vendedor_nome']);
        }

        if ($normalized['cliente_nome']) {
            $query->where('cliente_nome', $normalized['cliente_nome']);
        }

        return $query;
    }

    protected function despesasQuery(array $filters, bool $withRelations = true): Builder
    {
        $normalized = $this->normalizeFilters($filters);
        $period = $this->resolvePeriod($normalized);
        $query = DespesaOperacional::query();

        if ($withRelations) {
            $query->with(['produto', 'user']);
        }

        if (! $period['ok']) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereBetween('data_competencia', [
            $period['from']->toDateString(),
            $period['to']->toDateString(),
        ]);

        if ($normalized['produto_id']) {
            $query->where('produto_id', $normalized['produto_id']);
        }

        if ($normalized['categoria_despesa']) {
            $query->where('categoria', $normalized['categoria_despesa']);
        }

        if ($normalized['categoria_produto_id']) {
            $query->whereHas('produto', fn (Builder $builder) => $builder->where('categoria_produto_id', $normalized['categoria_produto_id']));
        }

        return $query;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
