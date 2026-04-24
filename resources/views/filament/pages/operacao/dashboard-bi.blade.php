<x-filament-panels::page>
    @php($snapshot = $this->snapshot)
    @php($salesMax = collect($snapshot['sales_by_day'] ?? [])->max('receita') ?: 1)
    @php($expenseMax = collect($snapshot['expenses_by_category'] ?? [])->max('valor') ?: 1)

    <div class="oa-root">
        <section class="oa-hero">
            <div>
                <p class="oa-eyebrow">Operacao</p>
                <h1 class="oa-title">Dashboard BI</h1>
                <p class="oa-subtitle">
                    Painel executivo com indicadores, ranking de produtos, vendedores, clientes e alertas de estoque.
                </p>
            </div>
        </section>

        <section class="oa-panel">
            <div class="oa-panel-head">
                <div>
                    <h2>Filtros</h2>
                    <p>Recorte principal do BI operacional.</p>
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
            </div>
        </section>

        @if (! ($snapshot['ok'] ?? false))
            <section class="oa-panel oa-panel--alert">
                {{ $snapshot['error'] ?? 'Nao foi possivel carregar o dashboard BI.' }}
            </section>
        @else
            <section class="oa-kpi-grid">
                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Receita bruta</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['receita_bruta']) }}</strong>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Receita liquida</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['receita_liquida']) }}</strong>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">CMV</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['cmv']) }}</strong>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Despesas</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['despesas_operacionais']) }}</strong>
                </article>

                <article class="oa-kpi-card oa-kpi-card--highlight">
                    <span class="oa-kpi-label">Lucro liquido</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['lucro_liquido']) }}</strong>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Margem</span>
                    <strong class="oa-kpi-value">{{ $this->pct($snapshot['metrics']['margem_liquida_perc']) }}</strong>
                </article>
            </section>

            <section class="oa-grid-2">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Vendas por dia</h2>
                            <p>Receita bruta por dia dentro do recorte atual.</p>
                        </div>
                    </div>

                    <div class="oa-bars">
                        @forelse ($snapshot['sales_by_day'] as $row)
                            <div class="oa-bar-row">
                                <span>{{ $row['label'] }}</span>
                                <div class="oa-bar-track">
                                    <div class="oa-bar-fill" style="width: {{ $this->barWidth($row['receita'], $salesMax) }}%;"></div>
                                </div>
                                <strong>{{ $this->money($row['receita']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem vendas no periodo filtrado.</p>
                        @endforelse
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Despesas por categoria</h2>
                            <p>Participacao das despesas operacionais no periodo.</p>
                        </div>
                    </div>

                    <div class="oa-bars">
                        @forelse ($snapshot['expenses_by_category'] as $row)
                            <div class="oa-bar-row">
                                <span>{{ $row['label'] }}</span>
                                <div class="oa-bar-track">
                                    <div class="oa-bar-fill oa-bar-fill--secondary" style="width: {{ $this->barWidth($row['valor'], $expenseMax) }}%;"></div>
                                </div>
                                <strong>{{ $this->money($row['valor']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem despesas no periodo filtrado.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="oa-grid-3">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Top produtos</h2>
                            <p>Maiores receitas no recorte atual.</p>
                        </div>
                    </div>

                    <div class="oa-mini-table">
                        @forelse ($snapshot['top_products'] as $row)
                            <div>
                                <span>{{ $row['produto_nome'] }}</span>
                                <strong>{{ $this->money($row['receita_bruta']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem produtos ranqueados.</p>
                        @endforelse
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Vendedores</h2>
                            <p>Receita e lucro por responsavel comercial.</p>
                        </div>
                    </div>

                    <div class="oa-mini-table">
                        @forelse ($snapshot['seller_performance'] as $row)
                            <div>
                                <span>{{ $row['nome'] }}</span>
                                <strong>{{ $this->money($row['receita']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem vendedores no periodo filtrado.</p>
                        @endforelse
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Clientes</h2>
                            <p>Principais contas do recorte operacional.</p>
                        </div>
                    </div>

                    <div class="oa-mini-table">
                        @forelse ($snapshot['client_ranking'] as $row)
                            <div>
                                <span>{{ $row['nome'] }}</span>
                                <strong>{{ $this->money($row['receita']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem clientes ranqueados.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="oa-grid-2">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Alertas de estoque</h2>
                            <p>Produtos com estoque igual ou abaixo do minimo cadastrado.</p>
                        </div>
                    </div>

                    <div class="oa-alert-list">
                        @forelse ($snapshot['low_stock_products'] as $row)
                            <div>
                                <div>
                                    <strong>{{ $row['nome'] }}</strong>
                                    <span>{{ $row['codigo'] ?: 'Sem codigo' }}</span>
                                </div>
                                <strong>{{ $this->qty($row['estoque_atual']) }} / min {{ $this->qty($row['estoque_minimo']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Nenhum produto em estado de alerta.</p>
                        @endforelse
                    </div>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Movimentacoes recentes</h2>
                            <p>Ultimos eventos que alteraram saldos de produtos e insumos.</p>
                        </div>
                    </div>

                    <div class="oa-mini-table">
                        @forelse ($snapshot['recent_movements'] as $row)
                            <div>
                                <span>{{ $row['origem'] }} · {{ $row['item'] }}</span>
                                <strong>{{ $this->qty($row['impacto']) }}</strong>
                            </div>
                        @empty
                            <p class="oa-empty">Sem movimentacoes recentes.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif
    </div>
</x-filament-panels::page>
