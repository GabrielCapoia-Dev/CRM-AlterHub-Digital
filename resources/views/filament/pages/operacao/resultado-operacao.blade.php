<x-filament-panels::page>
    @include('filament.partials.stock-theme-styles')

    @php($snapshot = $this->snapshot)
    @php($detailRows = $this->detailRows)

    <div class="crm-resource-page oa-root">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Operacao</p>
                    <h2 class="crm-resource-hero__title">Resultado (DRE)</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Demonstrativo em tempo real com vendas, impostos, CMV e despesas do periodo selecionado.
                </p>
            </div>
        </section>

        <section class="oa-panel">
            <div class="oa-panel-head">
                <div>
                    <h2>Filtros</h2>
                    <p>Mes calendario ou intervalo de datas, com recorte por produto e categoria de despesa.</p>
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

                <label class="oa-field oa-field--wide">
                    <span>Categoria despesa</span>
                    <select wire:model.live="categoriaDespesa">
                        <option value="">Todas</option>
                        @foreach ($this->expenseCategoryOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        @if (! ($snapshot['ok'] ?? false))
            <section class="oa-panel oa-panel--alert">
                {{ $snapshot['error'] ?? 'Nao foi possivel montar o resultado do periodo.' }}
            </section>
        @else
            <section class="oa-kpi-grid oa-kpi-grid--triple">
                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Receita do periodo</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['receita_bruta']) }}</strong>
                    <p class="oa-kpi-note">Liquida: {{ $this->money($snapshot['metrics']['receita_liquida']) }}</p>
                </article>

                <article class="oa-kpi-card">
                    <span class="oa-kpi-label">Lucro liquido</span>
                    <strong class="oa-kpi-value">{{ $this->money($snapshot['metrics']['lucro_liquido']) }}</strong>
                    <p class="oa-kpi-note">Lucro bruto: {{ $this->money($snapshot['metrics']['lucro_bruto']) }}</p>
                </article>

                <article class="oa-kpi-card oa-kpi-card--highlight">
                    <span class="oa-kpi-label">Margem sobre receita liquida</span>
                    <strong class="oa-kpi-value">{{ $this->pct($snapshot['metrics']['margem_liquida_perc']) }}</strong>
                    <p class="oa-kpi-note">{{ $snapshot['period']['label'] }}</p>
                </article>
            </section>

            <section class="oa-grid-2">
                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Demonstrativo</h2>
                            <p>{{ $snapshot['metrics']['qtd_vendas'] }} venda(s) e {{ $snapshot['metrics']['qtd_despesas'] }} despesa(s)</p>
                        </div>
                    </div>

                    <table class="oa-dre">
                        <tbody>
                            <tr>
                                <td>Receita bruta</td>
                                <td>{{ $this->money($snapshot['metrics']['receita_bruta']) }}</td>
                            </tr>
                            <tr>
                                <td>(-) ICMS</td>
                                <td>{{ $this->money($snapshot['metrics']['icms_total']) }}</td>
                            </tr>
                            <tr>
                                <td>(-) Outros impostos</td>
                                <td>{{ $this->money($snapshot['metrics']['outros_impostos_total']) }}</td>
                            </tr>
                            <tr class="is-total">
                                <td>Receita liquida</td>
                                <td>{{ $this->money($snapshot['metrics']['receita_liquida']) }}</td>
                            </tr>
                            <tr>
                                <td>(-) CMV</td>
                                <td>{{ $this->money($snapshot['metrics']['cmv']) }}</td>
                            </tr>
                            <tr class="is-total">
                                <td>Lucro bruto</td>
                                <td>{{ $this->money($snapshot['metrics']['lucro_bruto']) }}</td>
                            </tr>
                            <tr>
                                <td>(-) Despesas operacionais</td>
                                <td>{{ $this->money($snapshot['metrics']['despesas_operacionais']) }}</td>
                            </tr>
                            <tr class="is-final">
                                <td>Lucro liquido</td>
                                <td>{{ $this->money($snapshot['metrics']['lucro_liquido']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </article>

                <article class="oa-panel">
                    <div class="oa-panel-head">
                        <div>
                            <h2>Detalhamento</h2>
                            <p>Escolha qual bloco do resultado deseja inspecionar.</p>
                        </div>
                    </div>

                    <div class="oa-segmented">
                        <button type="button" class="{{ $detailView === 'receita' ? 'is-active' : '' }}" wire:click="showDetail('receita')">Receita</button>
                        <button type="button" class="{{ $detailView === 'cmv' ? 'is-active' : '' }}" wire:click="showDetail('cmv')">CMV</button>
                        <button type="button" class="{{ $detailView === 'despesas' ? 'is-active' : '' }}" wire:click="showDetail('despesas')">Despesas</button>
                    </div>

                    <div class="oa-table-wrap">
                        <table class="oa-table">
                            <thead>
                                @if ($detailView === 'cmv')
                                    <tr>
                                        <th>Produto</th>
                                        <th class="is-num">Qtd</th>
                                        <th class="is-num">CMV</th>
                                        <th class="is-num">Receita</th>
                                    </tr>
                                @elseif ($detailView === 'despesas')
                                    <tr>
                                        <th>Data</th>
                                        <th>Descricao</th>
                                        <th>Categoria</th>
                                        <th class="is-num">Valor</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th class="is-num">Receita</th>
                                        <th class="is-num">Impostos</th>
                                        <th class="is-num">Lucro</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @forelse ($detailRows as $row)
                                    @if ($detailView === 'cmv')
                                        <tr>
                                            <td>
                                                <strong>{{ $row['produto_nome'] }}</strong>
                                                <span>{{ $row['produto_codigo'] ?: 'Sem codigo' }}</span>
                                            </td>
                                            <td class="is-num">{{ $this->qty($row['qtd']) }}</td>
                                            <td class="is-num">{{ $this->money($row['custo_total']) }}</td>
                                            <td class="is-num">{{ $this->money($row['receita_bruta']) }}</td>
                                        </tr>
                                    @elseif ($detailView === 'despesas')
                                        <tr>
                                            <td>{{ $this->humanDate($row['data']) }}</td>
                                            <td>
                                                <strong>{{ $row['descricao'] }}</strong>
                                                <span>{{ $row['subcategoria'] ?: 'Sem subcategoria' }}</span>
                                            </td>
                                            <td>{{ $row['categoria'] }}</td>
                                            <td class="is-num">{{ $this->money($row['valor']) }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td>{{ $this->humanDate($row['data']) }}</td>
                                            <td>
                                                <strong>{{ $row['produto_nome'] }}</strong>
                                                <span>{{ $row['produto_codigo'] ?: 'Sem codigo' }}</span>
                                            </td>
                                            <td class="is-num">{{ $this->money($row['receita_bruta']) }}</td>
                                            <td class="is-num">{{ $this->money($row['impostos']) }}</td>
                                            <td class="is-num">{{ $this->money($row['lucro_apos_impostos']) }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="4" class="oa-table-empty">Sem dados para este detalhamento no filtro atual.</td>
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
