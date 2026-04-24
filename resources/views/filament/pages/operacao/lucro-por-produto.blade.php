<x-filament-panels::page>
    @php($rows = $this->rows)
    @php($drilldown = $this->drilldown)
    @php($best = collect($rows)->sortByDesc('lucro_liquido')->first())
    @php($bestMargin = collect($rows)->filter(fn ($row) => $row['margem_lucro_liquido_perc'] !== null)->sortByDesc('margem_lucro_liquido_perc')->first())
    @php($worstMargin = collect($rows)->filter(fn ($row) => $row['margem_lucro_liquido_perc'] !== null)->sortBy('margem_lucro_liquido_perc')->first())

    <div class="oa-root">
        <section class="oa-hero">
            <div>
                <p class="oa-eyebrow">Operacao</p>
                <h1 class="oa-title">Lucro por produto</h1>
                <p class="oa-subtitle">
                    Ranking de desempenho por SKU com receitas, impostos, CMV e despesas alocadas.
                </p>
            </div>
        </section>

        <section class="oa-panel">
            <div class="oa-panel-head">
                <div>
                    <h2>Filtros</h2>
                    <p>Combine periodo, categoria, vendedor e cliente para analisar a rentabilidade.</p>
                </div>
            </div>

            <div class="oa-filter-grid">
                <label class="oa-field">
                    <span>Modo</span>
                    <select wire:model.live="periodMode">
                        <option value="mes">Mes</option>
                        <option value="intervalo">Intervalo</option>
                    </select>
                </label>

                @if ($periodMode === 'mes')
                    <label class="oa-field">
                        <span>Mes</span>
                        <select wire:model.live="month">
                            @foreach ($this->monthOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="oa-field">
                        <span>Ano</span>
                        <select wire:model.live="year">
                            @foreach ($this->yearOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="oa-field">
                        <span>Data inicial</span>
                        <input type="date" wire:model.live="dateFrom">
                    </label>

                    <label class="oa-field">
                        <span>Data final</span>
                        <input type="date" wire:model.live="dateTo">
                    </label>
                @endif

                <label class="oa-field oa-field--wide">
                    <span>Produto</span>
                    <select wire:model.live="produtoId">
                        <option value="">Todos</option>
                        @foreach ($this->productOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="oa-field">
                    <span>Categoria produto</span>
                    <select wire:model.live="categoriaProdutoId">
                        <option value="">Todas</option>
                        @foreach ($this->productCategoryOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="oa-field">
                    <span>Categoria despesa</span>
                    <select wire:model.live="categoriaDespesa">
                        <option value="">Todas</option>
                        @foreach ($this->expenseCategoryOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="oa-field">
                    <span>Vendedor</span>
                    <select wire:model.live="vendedorNome">
                        <option value="">Todos</option>
                        @foreach ($this->sellerOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="oa-field">
                    <span>Cliente</span>
                    <select wire:model.live="clienteNome">
                        <option value="">Todos</option>
                        @foreach ($this->clientOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="oa-field">
                    <span>Ordenar</span>
                    <select wire:model.live="sort">
                        <option value="lucro_liquido">Maior lucro liquido</option>
                        <option value="receita">Maior receita</option>
                        <option value="margem">Maior margem</option>
                        <option value="volume">Maior volume</option>
                        <option value="pior">Pior desempenho</option>
                    </select>
                </label>
            </div>
        </section>

        <section class="oa-kpi-grid">
            <article class="oa-kpi-card">
                <span class="oa-kpi-label">Mais lucrativo</span>
                <strong class="oa-kpi-value">{{ $best['produto_nome'] ?? '—' }}</strong>
                <p class="oa-kpi-note">{{ $best ? $this->money($best['lucro_liquido']) : 'Sem dados' }}</p>
            </article>

            <article class="oa-kpi-card">
                <span class="oa-kpi-label">Maior margem</span>
                <strong class="oa-kpi-value">{{ $bestMargin['produto_nome'] ?? '—' }}</strong>
                <p class="oa-kpi-note">{{ $bestMargin ? $this->pct($bestMargin['margem_lucro_liquido_perc']) : 'Sem dados' }}</p>
            </article>

            <article class="oa-kpi-card oa-kpi-card--highlight">
                <span class="oa-kpi-label">Pior margem</span>
                <strong class="oa-kpi-value">{{ $worstMargin['produto_nome'] ?? '—' }}</strong>
                <p class="oa-kpi-note">{{ $worstMargin ? $this->pct($worstMargin['margem_lucro_liquido_perc']) : 'Sem dados' }}</p>
            </article>
        </section>

        <section class="oa-panel">
            <div class="oa-panel-head">
                <div>
                    <h2>Ranking por produto</h2>
                    <p>Clique em um produto para abrir o historico resumido abaixo.</p>
                </div>
            </div>

            <div class="oa-table-wrap">
                <table class="oa-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="is-num">Qtd</th>
                            <th class="is-num">Rec. bruta</th>
                            <th class="is-num">Impostos</th>
                            <th class="is-num">Rec. liquida</th>
                            <th class="is-num">CMV</th>
                            <th class="is-num">Desp.</th>
                            <th class="is-num">Lucro</th>
                            <th class="is-num">Margem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:click="selectProduto({{ $row['produto_id'] }})" class="oa-row-button">
                                <td>
                                    <strong>{{ $row['produto_nome'] }}</strong>
                                    <span>{{ $row['produto_codigo'] ?: 'Sem codigo' }}</span>
                                </td>
                                <td class="is-num">{{ $this->qty($row['qtd']) }}</td>
                                <td class="is-num">{{ $this->money($row['receita_bruta']) }}</td>
                                <td class="is-num">{{ $this->money($row['impostos']) }}</td>
                                <td class="is-num">{{ $this->money($row['receita_liquida']) }}</td>
                                <td class="is-num">{{ $this->money($row['cmv']) }}</td>
                                <td class="is-num">{{ $this->money($row['despesas_alocadas']) }}</td>
                                <td class="is-num">{{ $this->money($row['lucro_liquido']) }}</td>
                                <td class="is-num">{{ $this->pct($row['margem_lucro_liquido_perc']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="oa-table-empty">Nenhum produto encontrado com os filtros informados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($selectedProdutoId)
            <section class="oa-grid-2">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Historico de vendas</h2>
                            <p>Detalhamento do produto selecionado.</p>
                        </div>
                    </div>

                    <div class="oa-table-wrap">
                        <table class="oa-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th class="is-num">Qtd</th>
                                    <th class="is-num">Receita</th>
                                    <th class="is-num">Custo</th>
                                    <th class="is-num">Lucro</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldown['vendas'] as $row)
                                    <tr>
                                        <td>{{ $this->humanDate($row['data']) }}</td>
                                        <td class="is-num">{{ $this->qty($row['quantidade']) }}</td>
                                        <td class="is-num">{{ $this->money($row['receita_bruta']) }}</td>
                                        <td class="is-num">{{ $this->money($row['custo_total']) }}</td>
                                        <td class="is-num">{{ $this->money($row['lucro_apos_impostos']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="oa-table-empty">Sem vendas para este produto.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Despesas alocadas</h2>
                            <p>Lancamentos vinculados ao SKU no mesmo periodo.</p>
                        </div>
                    </div>

                    <div class="oa-table-wrap">
                        <table class="oa-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descricao</th>
                                    <th class="is-num">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($drilldown['despesas'] as $row)
                                    <tr>
                                        <td>{{ $this->humanDate($row['data']) }}</td>
                                        <td>
                                            <strong>{{ $row['descricao'] }}</strong>
                                            <span>{{ $row['categoria'] }}{{ $row['subcategoria'] ? ' · ' . $row['subcategoria'] : '' }}</span>
                                        </td>
                                        <td class="is-num">{{ $this->money($row['valor']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="oa-table-empty">Sem despesas alocadas para este produto.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        @endif
    </div>
</x-filament-panels::page>
