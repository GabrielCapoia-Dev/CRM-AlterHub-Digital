<x-filament-panels::page>
    @php($snapshot = $this->snapshot)

    <div class="oa-root">
        <section class="oa-hero">
            <div>
                <p class="oa-eyebrow">Operacao</p>
                <h1 class="oa-title">Visao consolidada</h1>
                <p class="oa-subtitle">
                    Leitura rapida da operacao comercial com receita, custo, despesas e lucro no mesmo painel.
                </p>
            </div>
        </section>

        <section class="oa-panel">
            <div class="oa-panel-head">
                <div>
                    <h2>Filtros</h2>
                    <p>Use os mesmos recortes do restante do modulo operacional.</p>
                </div>
            </div>

            <div class="oa-filter-grid oa-filter-grid--compact">
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

                <label class="oa-field oa-field--wide">
                    <span>Produto</span>
                    <select wire:model.live="produtoId">
                        <option value="">Todos</option>
                        @foreach ($this->productOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        @if (! ($snapshot['ok'] ?? false))
            <section class="oa-panel oa-panel--alert">
                {{ $snapshot['error'] ?? 'Nao foi possivel carregar a visao consolidada.' }}
            </section>
        @else
            <section class="oa-kpi-grid">
                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Receita bruta</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['receita_bruta']) }}</strong>
                    <p class="oa-kpi-note">{{ $snapshot['metrics']['qtd_vendas'] }} venda(s) no periodo</p>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Receita liquida</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['receita_liquida']) }}</strong>
                    <p class="oa-kpi-note">Impostos: {{ $this->money($snapshot['metrics']['icms_total'] + $snapshot['metrics']['outros_impostos_total']) }}</p>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">CMV</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['cmv']) }}</strong>
                    <p class="oa-kpi-note">Custo dos produtos vendidos</p>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Despesas</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['despesas_operacionais']) }}</strong>
                    <p class="oa-kpi-note">{{ $snapshot['metrics']['qtd_despesas'] }} lancamento(s)</p>
                </article>

                <article class="oa-kpi-card oa-kpi-card--highlight">
                    <span class="oa-kpi-label">Lucro liquido</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['lucro_liquido']) }}</strong>
                    <p class="oa-kpi-note">Margem: {{ $this->pct($snapshot['metrics']['margem_liquida_perc']) }}</p>
                </article>
            </section>

            <section class="oa-grid-2">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Resumo</h2>
                            <p>{{ $snapshot['period']['label'] }}</p>
                        </div>
                    </div>

                    <div class="oa-stat-list">
                        <div>
                            <span>Lucro bruto</span>
                            <strong>{{ $this->money($snapshot['metrics']['lucro_bruto']) }}</strong>
                        </div>

                        <div>
                            <span>Lucro operacional</span>
                            <strong>{{ $this->money($snapshot['metrics']['lucro_operacional']) }}</strong>
                        </div>

                        <div>
                            <span>Produto destaque</span>
                            <strong>{{ $snapshot['top_product']['produto_nome'] ?? 'Sem vendas no periodo' }}</strong>
                        </div>
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Produto em destaque</h2>
                            <p>Maior lucro liquido dentro do filtro atual.</p>
                        </div>
                    </div>

                    @if ($snapshot['top_product'])
                        <div class="oa-highlight-card">
                            <span class="oa-chip">{{ $snapshot['top_product']['produto_codigo'] ?: 'SKU' }}</span>
                            <h3>{{ $snapshot['top_product']['produto_nome'] }}</h3>
                            <p>Lucro liquido: {{ $this->money($snapshot['top_product']['lucro_liquido']) }}</p>
                            <p>Margem: {{ $this->pct($snapshot['top_product']['margem_lucro_liquido_perc']) }}</p>
                        </div>
                    @else
                        <p class="oa-empty">Sem vendas suficientes para destacar um produto no periodo filtrado.</p>
                    @endif
                </article>
            </section>

            <section class="oa-panel">
                <div class="oa-panel-head">
                    <div>
                        <h2>Lucro por produto</h2>
                        <p>Ranking resumido da operacao com despesas alocadas por SKU.</p>
                    </div>
                </div>

                <div class="oa-table-wrap">
                    <table class="oa-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="is-num">Qtd</th>
                                <th class="is-num">Receita</th>
                                <th class="is-num">CMV</th>
                                <th class="is-num">Desp.</th>
                                <th class="is-num">Lucro</th>
                                <th class="is-num">Margem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($snapshot['profit_rows'] as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['produto_nome'] }}</strong>
                                        <span>{{ $row['produto_codigo'] ?: 'Sem codigo' }}</span>
                                    </td>
                                    <td class="is-num">{{ $this->qty($row['qtd']) }}</td>
                                    <td class="is-num">{{ $this->money($row['receita_bruta']) }}</td>
                                    <td class="is-num">{{ $this->money($row['cmv']) }}</td>
                                    <td class="is-num">{{ $this->money($row['despesas_alocadas']) }}</td>
                                    <td class="is-num">{{ $this->money($row['lucro_liquido']) }}</td>
                                    <td class="is-num">{{ $this->pct($row['margem_lucro_liquido_perc']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="oa-table-empty">Nenhuma venda encontrada no periodo filtrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
