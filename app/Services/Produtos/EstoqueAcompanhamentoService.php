<?php

namespace App\Services\Produtos;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EstoqueAcompanhamentoService
{
    public const ITEM_PRODUTO = 'produto';

    public const ITEM_INSUMO = 'insumo';

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleTypes
     */
    public function paginateItems(
        array $filters,
        array $accessibleTypes,
        int $perPage = 12,
        string $pageName = 'itemsPage',
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = $this->applyItemFilters(
            $this->itemsQuery($accessibleTypes),
            $filters,
        );

        return $query
            ->orderByDesc('alerta_estoque')
            ->orderBy('nome')
            ->orderBy('item_tipo')
            ->orderBy('item_id')
            ->paginate($perPage, ['*'], $pageName, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleTypes
     */
    public function paginateMovements(
        array $filters,
        array $accessibleTypes,
        int $perPage = 20,
        string $pageName = 'movementsPage',
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = $this->applyMovementFilters(
            $this->movementsQuery($accessibleTypes),
            $filters,
        );

        return $query
            ->orderByDesc('realizado_em')
            ->orderByDesc('movimento_id')
            ->orderBy('item_tipo')
            ->paginate($perPage, ['*'], $pageName, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleTypes
     * @return array{itens:int, alertas:int, entradas:int, saidas:int, saidas_venda:int}
     */
    public function summary(array $filters, array $accessibleTypes): array
    {
        $items = $this->applyItemFilters($this->itemsQuery($accessibleTypes), $filters);
        $movements = $this->applyMovementFilters($this->movementsQuery($accessibleTypes), $filters);

        return [
            'itens' => (clone $items)->count(),
            'alertas' => (clone $items)->where('alerta_estoque', 1)->count(),
            'entradas' => (clone $movements)->where('impacto_estoque', '>', 0)->count(),
            'saidas' => (clone $movements)->where('impacto_estoque', '<', 0)->count(),
            'saidas_venda' => (clone $movements)
                ->where('impacto_estoque', '<', 0)
                ->where('origem_tipo', 'venda')
                ->count(),
        ];
    }

    /**
     * @param  list<string>  $accessibleTypes
     * @return array<string, string>
     */
    public function originOptions(array $accessibleTypes): array
    {
        return $this->movementsQuery($accessibleTypes)
            ->whereNotNull('origem_tipo')
            ->where('origem_tipo', '<>', '')
            ->select('origem_tipo')
            ->distinct()
            ->orderBy('origem_tipo')
            ->pluck('origem_tipo')
            ->mapWithKeys(fn (string $origin): array => [$origin => $this->originLabel($origin)])
            ->all();
    }

    /** @return array<string, string> */
    public function movementTypeOptions(): array
    {
        return [
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            'consumo_interno' => 'Consumo interno',
            'perda' => 'Perda',
        ];
    }

    public function movementTypeLabel(?string $type): string
    {
        if (blank($type)) {
            return 'Não definido';
        }

        return $this->movementTypeOptions()[$type] ?? Str::headline($type);
    }

    public function originLabel(?string $origin): string
    {
        if (blank($origin)) {
            return 'Sem origem informada';
        }

        return [
            'manual' => 'Lançamento manual',
            'venda' => 'Venda',
            'venda_confirmada' => 'Venda confirmada',
            'estorno' => 'Estorno',
            'remessa' => 'Remessa',
            'remessa_legada' => 'Remessa legada',
            'ordem_producao' => 'Ordem de produção',
            'devolucao' => 'Devolução',
        ][$origin] ?? Str::headline($origin);
    }

    /**
     * @param  list<string>  $accessibleTypes
     */
    protected function itemsQuery(array $accessibleTypes): Builder
    {
        $queries = [];

        if (in_array(self::ITEM_PRODUTO, $accessibleTypes, true)) {
            $queries[] = $this->productItemsQuery();
        }

        if (in_array(self::ITEM_INSUMO, $accessibleTypes, true)) {
            $queries[] = $this->inputItemsQuery();
        }

        return $this->fromUnion($queries, $this->emptyItemsQuery(), 'estoque_itens');
    }

    /**
     * @param  list<string>  $accessibleTypes
     */
    protected function movementsQuery(array $accessibleTypes): Builder
    {
        $queries = [];

        if (in_array(self::ITEM_PRODUTO, $accessibleTypes, true)) {
            $queries[] = $this->productMovementsQuery();
        }

        if (in_array(self::ITEM_INSUMO, $accessibleTypes, true)) {
            $queries[] = $this->inputMovementsQuery();
        }

        return $this->fromUnion($queries, $this->emptyMovementsQuery(), 'estoque_movimentacoes');
    }

    protected function productItemsQuery(): Builder
    {
        return DB::table('produtos as item')->select([
            DB::raw("'produto' as item_tipo"),
            'item.id as item_id',
            'item.codigo_interno as codigo',
            'item.nome',
            'item.unidade_medida as unidade',
            DB::raw('COALESCE(item.estoque_fisico, 0) as estoque_fisico'),
            DB::raw('COALESCE(item.estoque_reservado, 0) as estoque_reservado'),
            DB::raw('(COALESCE(item.estoque_fisico, 0) - COALESCE(item.estoque_reservado, 0)) as estoque_disponivel'),
            'item.estoque_minimo',
            DB::raw($this->stockAlertExpression('item').' as alerta_estoque'),
        ]);
    }

    protected function inputItemsQuery(): Builder
    {
        return DB::table('insumos as item')
            ->leftJoin('tipos_unidade_medida as unidade', 'unidade.id', '=', 'item.tipo_unidade_medida_id')
            ->select([
                DB::raw("'insumo' as item_tipo"),
                'item.id as item_id',
                'item.codigo_interno as codigo',
                'item.nome',
                DB::raw('COALESCE(unidade.sigla, unidade.nome) as unidade'),
                DB::raw('COALESCE(item.estoque_fisico, 0) as estoque_fisico'),
                DB::raw('COALESCE(item.estoque_reservado, 0) as estoque_reservado'),
                DB::raw('(COALESCE(item.estoque_fisico, 0) - COALESCE(item.estoque_reservado, 0)) as estoque_disponivel'),
                'item.estoque_minimo',
                DB::raw($this->stockAlertExpression('item').' as alerta_estoque'),
            ]);
    }

    protected function productMovementsQuery(): Builder
    {
        return DB::table('produto_movimentacoes as movement')
            ->join('produtos as item', 'item.id', '=', 'movement.produto_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'movement.user_id')
            ->leftJoin('vendas_operacao as sale_line', function (JoinClause $join): void {
                $join
                    ->on('sale_line.id', '=', 'movement.origem_id')
                    ->where('movement.origem_tipo', '=', 'venda');
            })
            ->leftJoin('venda_operacao_pedidos as sale_order', 'sale_order.id', '=', 'sale_line.venda_operacao_pedido_id')
            ->select([
                DB::raw("'produto' as item_tipo"),
                'movement.id as movimento_id',
                'item.id as item_id',
                'item.codigo_interno as codigo',
                'item.nome',
                'movement.tipo',
                'movement.origem_tipo',
                'movement.origem_id',
                'movement.quantidade',
                'movement.impacto_estoque',
                'movement.saldo_anterior',
                'movement.saldo_atual',
                'movement.unidade',
                'movement.documento_referencia',
                'movement.motivo',
                'movement.origem_destino',
                'movement.destino',
                DB::raw("COALESCE(NULLIF(movement.responsavel_nome, ''), actor.name) as responsavel"),
                'movement.valor_total',
                'movement.observacao',
                'movement.realizado_em',
                'movement.estornada_em',
                'movement.estorno_de_id',
                DB::raw('COALESCE(sale_order.codigo, movement.documento_referencia) as venda_documento'),
            ]);
    }

    protected function inputMovementsQuery(): Builder
    {
        return DB::table('insumo_movimentacoes as movement')
            ->join('insumos as item', 'item.id', '=', 'movement.insumo_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'movement.user_id')
            ->select([
                DB::raw("'insumo' as item_tipo"),
                'movement.id as movimento_id',
                'item.id as item_id',
                'item.codigo_interno as codigo',
                'item.nome',
                'movement.tipo',
                'movement.origem_tipo',
                'movement.origem_id',
                'movement.quantidade',
                'movement.impacto_estoque',
                'movement.saldo_anterior',
                'movement.saldo_atual',
                'movement.unidade',
                'movement.documento_referencia',
                'movement.motivo',
                'movement.origem_destino',
                'movement.destino',
                DB::raw("COALESCE(NULLIF(movement.responsavel_nome, ''), actor.name) as responsavel"),
                'movement.valor_total',
                'movement.observacao',
                'movement.realizado_em',
                'movement.estornada_em',
                'movement.estorno_de_id',
                DB::raw('NULL as venda_documento'),
            ]);
    }

    protected function emptyItemsQuery(): Builder
    {
        return $this->productItemsQuery()->whereRaw('1 = 0');
    }

    protected function emptyMovementsQuery(): Builder
    {
        return $this->productMovementsQuery()->whereRaw('1 = 0');
    }

    /**
     * @param  list<Builder>  $queries
     */
    protected function fromUnion(array $queries, Builder $emptyQuery, string $alias): Builder
    {
        $union = array_shift($queries) ?? $emptyQuery;

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, $alias);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyItemFilters(Builder $query, array $filters): Builder
    {
        $itemType = (string) ($filters['item_type'] ?? '');

        if (in_array($itemType, [self::ITEM_PRODUTO, self::ITEM_INSUMO], true)) {
            $query->where('item_tipo', $itemType);
        }

        $search = Str::lower(trim((string) ($filters['item_search'] ?? '')));

        if ($search !== '') {
            // SQLite sem a extensao ICU aplica case folding apenas a caracteres ASCII.
            // Mantemos SQL portavel aqui; buscas acentuadas podem ser sensiveis a caixa
            // nesse driver, enquanto o MySQL usa a collation configurada pela aplicacao.
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereRaw('LOWER(nome) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(COALESCE(codigo, ?)) LIKE ?', ['', "%{$search}%"]);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyMovementFilters(Builder $query, array $filters): Builder
    {
        $this->applyItemFilters($query, $filters);

        $movementType = trim((string) ($filters['movement_type'] ?? ''));
        if ($movementType !== '') {
            $query->where('tipo', $movementType);
        }

        $originType = trim((string) ($filters['origin_type'] ?? ''));
        if ($originType !== '') {
            $query->where('origem_tipo', $originType);
        }

        if ($dateFrom = $this->normalizeDate($filters['date_from'] ?? null)) {
            $query->where('realizado_em', '>=', $dateFrom->startOfDay()->format('Y-m-d H:i:s'));
        }

        if ($dateTo = $this->normalizeDate($filters['date_to'] ?? null)) {
            $query->where('realizado_em', '<', $dateTo->addDay()->startOfDay()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    protected function stockAlertExpression(string $tableAlias): string
    {
        return "CASE
            WHEN {$tableAlias}.estoque_minimo IS NOT NULL
                AND {$tableAlias}.estoque_minimo > 0
                AND (COALESCE({$tableAlias}.estoque_fisico, 0) - COALESCE({$tableAlias}.estoque_reservado, 0)) <= {$tableAlias}.estoque_minimo
            THEN 1
            ELSE 0
        END";
    }

    protected function normalizeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
