<x-filament-panels::page>
    @include('filament.partials.stock-theme-styles')

    @php($summary = $this->summary)
    @php($items = $this->items)
    @php($movements = $this->movements)
    @php($itemOptions = $this->itemOptions())
    @php($selectedOverview = $this->selectedItemOverview)
    @php($hasActiveFilters = collect($this->filters())->contains(fn ($value) => filled($value)))

    <div class="crm-resource-page stock-monitor">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Estoque</p>
                    <h2 class="crm-resource-hero__title">Acompanhamento unificado</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Saldos de produtos e insumos em uma visão direta, com o histórico completo de entradas e saídas.
                </p>
            </div>
        </section>

        <section class="stock-panel stock-filters" aria-labelledby="stock-filter-title">
            <div class="stock-panel__header">
                <div>
                    <h3 id="stock-filter-title">Filtros</h3>
                    <p>Localize um item e refine os eventos por operação, origem ou período.</p>
                </div>

                @if ($hasActiveFilters)
                    <button type="button" class="stock-clear" wire:click="resetFilters">
                        Limpar filtros
                    </button>
                @endif
            </div>

            <div class="stock-filter-grid">
                <div class="stock-filter-group-title">
                    <strong>Itens</strong>
                    <span>Busque livremente ou selecione um item exato para abrir seu dossiê completo.</span>
                </div>

                @if (count($this->itemTypeOptions()) > 1)
                    <label class="stock-field">
                        <span>Categoria</span>
                        <select wire:model.live="itemType">
                            <option value="">Produtos e insumos</option>
                            @foreach ($this->itemTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="stock-field stock-field--wide">
                    <span>Item específico</span>
                    <select wire:model.live="selectedItem">
                        <option value="">Todos os itens</option>
                        @foreach ($itemOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="stock-field stock-field--wide">
                    <span>Busca rápida</span>
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="itemSearch"
                        placeholder="Nome ou código"
                        autocomplete="off"
                    >
                </label>

                <div class="stock-filter-group-title stock-filter-group-title--timeline">
                    <strong>Histórico</strong>
                    <span>Refine a timeline sem alterar os saldos atuais exibidos.</span>
                </div>

                <label class="stock-field">
                    <span>Tipo de movimentação</span>
                    <select wire:model.live="movementType">
                        <option value="">Entradas e saídas</option>
                        @foreach ($this->movementTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="stock-field">
                    <span>Impacto no saldo</span>
                    <select wire:model.live="impactDirection">
                        <option value="">Qualquer impacto</option>
                        <option value="entrada">Somente entradas</option>
                        <option value="saida">Somente saídas</option>
                        <option value="neutro">Sem impacto</option>
                    </select>
                </label>

                <label class="stock-field">
                    <span>Origem</span>
                    <select wire:model.live="originType">
                        <option value="">Todas as origens</option>
                        @foreach ($this->originOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="stock-field">
                    <span>Documento, responsável ou observação</span>
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="movementSearch"
                        placeholder="Ex.: nota fiscal, pedido, operador..."
                        autocomplete="off"
                    >
                </label>

                <label class="stock-field">
                    <span>De</span>
                    <input type="date" wire:model.live="dateFrom">
                </label>

                <label class="stock-field">
                    <span>Até</span>
                    <input type="date" wire:model.live="dateTo">
                </label>
            </div>
        </section>

        <section class="stock-kpis" aria-label="Resumo do estoque filtrado">
            <article class="stock-kpi">
                <span>Itens acompanhados</span>
                <strong>{{ number_format($summary['itens'], 0, ',', '.') }}</strong>
                <small>Produtos e insumos visíveis</small>
            </article>

            <article class="stock-kpi {{ $summary['alertas'] > 0 ? 'stock-kpi--alert' : '' }}">
                <span>Abaixo do mínimo</span>
                <strong>{{ number_format($summary['alertas'], 0, ',', '.') }}</strong>
                <small>Considera o saldo disponível</small>
            </article>

            <article class="stock-kpi stock-kpi--inbound">
                <span>Entradas no histórico</span>
                <strong>{{ number_format($summary['entradas'], 0, ',', '.') }}</strong>
                <small>Eventos com impacto positivo</small>
            </article>

            <article class="stock-kpi stock-kpi--outbound">
                <span>Saídas no histórico</span>
                <strong>{{ number_format($summary['saidas'], 0, ',', '.') }}</strong>
                <small>{{ number_format($summary['saidas_venda'], 0, ',', '.') }} por venda</small>
            </article>
        </section>

        @if ($selectedOverview)
            @php($selected = $selectedOverview['item'])
            <section class="stock-selected" aria-labelledby="stock-selected-title">
                <div class="stock-selected__header">
                    <div>
                        <div class="stock-selected__badges">
                            <span class="stock-kind stock-kind--{{ $selected->item_tipo }}">
                                {{ $this->itemTypeLabel($selected->item_tipo) }}
                            </span>
                            @if ((int) $selected->alerta_estoque === 1)
                                <span class="stock-status stock-status--alert">Abaixo do mínimo</span>
                            @else
                                <span class="stock-status stock-status--ok">Estoque regular</span>
                            @endif
                        </div>
                        <h3 id="stock-selected-title">{{ $selected->nome }}</h3>
                        <p>{{ $selected->codigo ?: 'Sem código interno' }} · Unidade {{ $selected->unidade ?: 'un' }}</p>
                    </div>

                    <button type="button" class="stock-clear" wire:click="clearSelectedItem">
                        Ver todos os itens
                    </button>
                </div>

                <div class="stock-selected__grid">
                    <div class="stock-selected__metric">
                        <span>Estoque físico</span>
                        <strong>{{ $this->quantity($selected->estoque_fisico) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }}</small>
                    </div>
                    <div class="stock-selected__metric">
                        <span>Reservado</span>
                        <strong>{{ $this->quantity($selected->estoque_reservado) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }}</small>
                    </div>
                    <div class="stock-selected__metric stock-selected__metric--primary">
                        <span>Disponível</span>
                        <strong>{{ $this->quantity($selected->estoque_disponivel) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }}</small>
                    </div>
                    <div class="stock-selected__metric">
                        <span>Estoque mínimo</span>
                        <strong>{{ $selected->estoque_minimo === null ? '—' : $this->quantity($selected->estoque_minimo) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }}</small>
                    </div>
                </div>

                <div class="stock-selected__history">
                    <div>
                        <span>Eventos no recorte</span>
                        <strong>{{ number_format($selectedOverview['eventos_filtrados'], 0, ',', '.') }}</strong>
                        <small>de {{ number_format($selectedOverview['eventos_total'], 0, ',', '.') }} em todo o histórico</small>
                    </div>
                    <div>
                        <span>Total de entradas</span>
                        <strong class="is-inbound">+{{ $this->quantity($selectedOverview['entradas_quantidade']) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }} no recorte atual</small>
                    </div>
                    <div>
                        <span>Total de saídas</span>
                        <strong class="is-outbound">-{{ $this->quantity($selectedOverview['saidas_quantidade']) }}</strong>
                        <small>{{ $selected->unidade ?: 'un' }} no recorte atual</small>
                    </div>
                    <div>
                        <span>Última movimentação</span>
                        <strong class="is-date">{{ $this->humanDate($selectedOverview['ultima_movimentacao']) }}</strong>
                        <small>
                            @if ($selectedOverview['primeira_movimentacao'])
                                Histórico desde {{ $this->humanDate($selectedOverview['primeira_movimentacao']) }}
                            @else
                                Nenhum evento registrado
                            @endif
                        </small>
                    </div>
                </div>
            </section>
        @endif

        <section class="stock-panel" aria-labelledby="stock-items-title">
            <div class="stock-panel__header">
                <div>
                    <h3 id="stock-items-title">Posição atual dos itens</h3>
                    <p>Saldo físico, reservas e disponibilidade sem misturar unidades de medida.</p>
                </div>

                <div class="stock-panel__tools">
                    <span class="stock-panel__count">
                        {{ number_format($items->total(), 0, ',', '.') }} {{ $items->total() === 1 ? 'item' : 'itens' }}
                    </span>
                    <label class="stock-page-size">
                        <span>Exibir</span>
                        <select wire:model.live="itemsPerPage" aria-label="Itens por página">
                            @foreach ($this->pageSizeOptions() as $pageSize)
                                <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" class="stock-export" wire:click="exportItemsCsv" wire:loading.attr="disabled">
                        Exportar posição CSV
                    </button>
                </div>
            </div>

            <div class="stock-table-wrap">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Físico</th>
                            <th>Reservado</th>
                            <th>Disponível</th>
                            <th>Mínimo</th>
                            <th>Situação</th>
                            <th><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr wire:key="stock-item-{{ $item->item_tipo }}-{{ $item->item_id }}">
                                <td>
                                    <div class="stock-item-name">
                                        <span class="stock-kind stock-kind--{{ $item->item_tipo }}">
                                            {{ $this->itemTypeLabel($item->item_tipo) }}
                                        </span>
                                        <strong>{{ $item->nome }}</strong>
                                        <small>{{ $item->codigo ?: 'Sem código interno' }}</small>
                                    </div>
                                </td>
                                <td class="stock-number">
                                    {{ $this->quantity($item->estoque_fisico) }}
                                    <small>{{ $item->unidade ?: 'un' }}</small>
                                </td>
                                <td class="stock-number">
                                    {{ $this->quantity($item->estoque_reservado) }}
                                    <small>{{ $item->unidade ?: 'un' }}</small>
                                </td>
                                <td class="stock-number stock-number--strong">
                                    {{ $this->quantity($item->estoque_disponivel) }}
                                    <small>{{ $item->unidade ?: 'un' }}</small>
                                </td>
                                <td class="stock-number">
                                    @if ($item->estoque_minimo !== null)
                                        {{ $this->quantity($item->estoque_minimo) }}
                                        <small>{{ $item->unidade ?: 'un' }}</small>
                                    @else
                                        <span class="stock-muted">Não definido</span>
                                    @endif
                                </td>
                                <td>
                                    @if ((int) $item->alerta_estoque === 1)
                                        <span class="stock-status stock-status--alert">Atenção</span>
                                    @else
                                        <span class="stock-status stock-status--ok">Regular</span>
                                    @endif
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="stock-history-link {{ $this->selectedItem === "{$item->item_tipo}:{$item->item_id}" ? 'is-active' : '' }}"
                                        wire:click="selectItem('{{ $item->item_tipo }}:{{ $item->item_id }}')"
                                    >
                                        {{ $this->selectedItem === "{$item->item_tipo}:{$item->item_id}" ? 'Selecionado' : 'Ver histórico' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="stock-empty">Nenhum item encontrado com os filtros atuais.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="stock-pagination">
                    <x-filament::pagination
                        :paginator="$items->onEachSide(1)"
                        wire:key="stock-items-pagination"
                    />
                </div>
            @endif
        </section>

        <section class="stock-panel" aria-labelledby="stock-timeline-title">
            <div class="stock-panel__header">
                <div>
                    <h3 id="stock-timeline-title">Timeline de movimentações</h3>
                    <p>Cada evento mostra o impacto no saldo e a operação que o originou.</p>
                </div>

                <div class="stock-panel__tools">
                    <span class="stock-panel__count">
                        {{ number_format($movements->total(), 0, ',', '.') }} {{ $movements->total() === 1 ? 'evento' : 'eventos' }}
                    </span>
                    <label class="stock-page-size">
                        <span>Exibir</span>
                        <select wire:model.live="movementsPerPage" aria-label="Eventos por página">
                            @foreach ($this->pageSizeOptions() as $pageSize)
                                <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" class="stock-export" wire:click="exportMovementsCsv" wire:loading.attr="disabled">
                        Exportar histórico CSV
                    </button>
                </div>
            </div>

            <div class="stock-timeline">
                @forelse ($movements as $movement)
                    @php($impact = (float) $movement->impacto_estoque)
                    @php($direction = $impact > 0 ? 'inbound' : ($impact < 0 ? 'outbound' : 'neutral'))

                    <article
                        class="stock-event stock-event--{{ $direction }} {{ $movement->origem_tipo === 'venda' ? 'stock-event--sale' : '' }}"
                        wire:key="stock-movement-{{ $movement->item_tipo }}-{{ $movement->movimento_id }}"
                    >
                        <div class="stock-event__axis" aria-hidden="true">
                            <span></span>
                        </div>

                        <div class="stock-event__body">
                            <div class="stock-event__top">
                                <div class="stock-event__identity">
                                    <div class="stock-event__badges">
                                        <span class="stock-kind stock-kind--{{ $movement->item_tipo }}">
                                            {{ $this->itemTypeLabel($movement->item_tipo) }}
                                        </span>
                                        <span class="stock-movement stock-movement--{{ $direction }}">
                                            {{ $this->movementTypeLabel($movement->tipo) }}
                                        </span>
                                        @if ($movement->estornada_em)
                                            <span class="stock-status stock-status--reversed">Estornada</span>
                                        @elseif ($movement->estorno_de_id)
                                            <span class="stock-status stock-status--reversal">Compensação</span>
                                        @endif
                                    </div>

                                    <h4>{{ $movement->nome }}</h4>
                                    <p>{{ $movement->codigo ?: 'Sem código interno' }} · {{ $this->humanDate($movement->realizado_em) }}</p>
                                </div>

                                <div class="stock-impact stock-impact--{{ $direction }}">
                                    <span>{{ $direction === 'inbound' ? 'Entrada' : ($direction === 'outbound' ? 'Saída' : 'Sem impacto') }}</span>
                                    <strong>{{ $this->impact($movement->impacto_estoque) }}</strong>
                                    <small>{{ $movement->unidade ?: 'un' }}</small>
                                </div>
                            </div>

                            <div class="stock-origin {{ $movement->origem_tipo === 'venda' ? 'stock-origin--sale' : '' }}">
                                <span>Origem</span>
                                <strong>{{ $this->originLabel($movement->origem_tipo, $movement->venda_documento) }}</strong>
                                @if ($movement->origem_tipo === 'venda')
                                    <small>Saída registrada pela confirmação da venda</small>
                                @elseif ($movement->origem_id)
                                    <small>Referência interna #{{ $movement->origem_id }}</small>
                                @endif
                            </div>

                            <dl class="stock-event__details">
                                <div>
                                    <dt>Saldo</dt>
                                    <dd>
                                        {{ $this->quantity($movement->saldo_anterior) }}
                                        <span aria-hidden="true">→</span>
                                        {{ $this->quantity($movement->saldo_atual) }} {{ $movement->unidade ?: 'un' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt>Documento</dt>
                                    <dd>{{ $movement->documento_referencia ?: 'Não informado' }}</dd>
                                </div>

                                <div>
                                    <dt>Responsável</dt>
                                    <dd>{{ $movement->responsavel ?: 'Não informado' }}</dd>
                                </div>

                                <div>
                                    <dt>Destino</dt>
                                    <dd>{{ $movement->destino ?: ($movement->origem_destino ?: 'Não informado') }}</dd>
                                </div>
                            </dl>

                            @if ($movement->motivo || $movement->observacao)
                                <div class="stock-event__note">
                                    @if ($movement->motivo)
                                        <strong>{{ $movement->motivo }}</strong>
                                    @endif
                                    @if ($movement->observacao)
                                        <p>{{ $movement->observacao }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="stock-empty stock-empty--timeline">
                        Nenhuma movimentação encontrada com os filtros atuais.
                    </div>
                @endforelse
            </div>

            @if ($movements->hasPages())
                <div class="stock-pagination">
                    <x-filament::pagination
                        :paginator="$movements->onEachSide(1)"
                        wire:key="stock-movements-pagination"
                    />
                </div>
            @endif
        </section>
    </div>

    @once
        <style>
            .stock-monitor {
                --stock-border: rgba(212, 216, 230, 0.82);
                --stock-muted: #6b7694;
                --stock-text: #0f1729;
                --stock-primary: #17368d;
                --stock-soft: #f7f9fc;
            }

            .stock-monitor *,
            .stock-monitor *::before,
            .stock-monitor *::after {
                box-sizing: border-box;
            }

            .stock-panel {
                min-width: 0;
                overflow: hidden;
                border: 1px solid var(--stock-border);
                border-radius: 1.25rem;
                background: #fff;
                box-shadow: 0 12px 30px rgba(15, 34, 97, 0.055);
            }

            .stock-panel__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.15rem 1.25rem;
                border-bottom: 1px solid rgba(212, 216, 230, 0.68);
            }

            .stock-panel__header h3 {
                margin: 0;
                color: var(--stock-text);
                font-size: 1rem;
                font-weight: 750;
                line-height: 1.35;
            }

            .stock-panel__header p {
                margin: 0.25rem 0 0;
                color: var(--stock-muted);
                font-size: 0.83rem;
                line-height: 1.5;
            }

            .stock-panel__count {
                flex: 0 0 auto;
                padding: 0.45rem 0.7rem;
                border-radius: 999px;
                background: #eef4ff;
                color: #1e45a8;
                font-size: 0.75rem;
                font-weight: 750;
                white-space: nowrap;
            }

            .stock-panel__tools {
                display: flex;
                flex: 0 0 auto;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
                gap: 0.55rem;
            }

            .stock-page-size {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                color: var(--stock-muted);
                font-size: 0.72rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .stock-page-size select {
                min-height: 2rem;
                border: 1px solid #dce3f2;
                border-radius: 0.6rem;
                outline: none;
                background: #fff;
                padding: 0.25rem 1.65rem 0.25rem 0.55rem;
                color: #17368d;
                font: inherit;
                font-weight: 750;
            }

            .stock-page-size select:focus {
                border-color: #5a8be6;
                box-shadow: 0 0 0 3px rgba(58, 109, 214, 0.12);
            }

            .stock-export {
                min-height: 2rem;
                border: 1px solid #cddbf4;
                border-radius: 0.65rem;
                background: #eef4ff;
                padding: 0.4rem 0.7rem;
                color: #17368d;
                font-size: 0.72rem;
                font-weight: 750;
                cursor: pointer;
                transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
            }

            .stock-export:hover {
                border-color: #9bb8ea;
                background: #e4efff;
                transform: translateY(-1px);
            }

            .stock-export:disabled {
                cursor: wait;
                opacity: 0.62;
                transform: none;
            }

            .stock-filters {
                overflow: visible;
            }

            .stock-filter-grid {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 0.85rem;
                padding: 1.15rem 1.25rem 1.25rem;
            }

            .stock-field {
                display: grid;
                min-width: 0;
                gap: 0.4rem;
            }

            .stock-field--wide {
                grid-column: span 2;
            }

            .stock-filter-group-title {
                display: flex;
                grid-column: 1 / -1;
                align-items: baseline;
                gap: 0.6rem;
                color: #17368d;
            }

            .stock-filter-group-title--timeline {
                margin-top: 0.2rem;
                border-top: 1px solid #edf0f6;
                padding-top: 0.85rem;
            }

            .stock-filter-group-title strong {
                font-size: 0.76rem;
                font-weight: 800;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .stock-filter-group-title span {
                color: #7a849e;
                font-size: 0.72rem;
            }

            .stock-field > span {
                color: #47516e;
                font-size: 0.72rem;
                font-weight: 700;
            }

            .stock-field input,
            .stock-field select {
                width: 100%;
                min-height: 2.55rem;
                border: 1px solid #d4d8e6;
                border-radius: 0.7rem;
                outline: none;
                background: #fff;
                padding: 0.6rem 0.7rem;
                color: var(--stock-text);
                font: inherit;
                font-size: 0.83rem;
                transition: border-color 0.16s ease, box-shadow 0.16s ease;
            }

            .stock-field input:focus,
            .stock-field select:focus {
                border-color: #5a8be6;
                box-shadow: 0 0 0 3px rgba(58, 109, 214, 0.12);
            }

            .stock-clear {
                flex: 0 0 auto;
                border: 0;
                background: transparent;
                padding: 0.35rem;
                color: #2a58c0;
                font-size: 0.78rem;
                font-weight: 750;
                cursor: pointer;
            }

            .stock-clear:hover {
                color: #17368d;
                text-decoration: underline;
            }

            .stock-selected {
                overflow: hidden;
                border: 1px solid #cddcf5;
                border-radius: 1.15rem;
                background: linear-gradient(135deg, #f7faff 0%, #fff 55%, #f2f7ff 100%);
                box-shadow: 0 12px 28px rgba(23, 54, 141, 0.07);
            }

            .stock-selected__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.15rem 1.25rem 0.9rem;
            }

            .stock-selected__badges {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }

            .stock-selected__header h3 {
                margin: 0.55rem 0 0;
                color: var(--stock-text);
                font-size: 1.15rem;
                font-weight: 800;
                line-height: 1.3;
            }

            .stock-selected__header p {
                margin: 0.2rem 0 0;
                color: var(--stock-muted);
                font-size: 0.75rem;
            }

            .stock-selected__grid,
            .stock-selected__history {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.7rem;
                padding: 0 1.25rem 1rem;
            }

            .stock-selected__metric,
            .stock-selected__history > div {
                display: grid;
                min-width: 0;
                grid-template-columns: 1fr auto;
                gap: 0.22rem 0.35rem;
                border: 1px solid rgba(205, 220, 245, 0.85);
                border-radius: 0.85rem;
                background: rgba(255, 255, 255, 0.9);
                padding: 0.8rem;
            }

            .stock-selected__metric--primary {
                border-color: #a9c4f1;
                background: #eef4ff;
            }

            .stock-selected__metric span,
            .stock-selected__history span {
                grid-column: 1 / -1;
                color: #6b7694;
                font-size: 0.64rem;
                font-weight: 750;
                letter-spacing: 0.045em;
                text-transform: uppercase;
            }

            .stock-selected__metric strong,
            .stock-selected__history strong {
                overflow-wrap: anywhere;
                color: var(--stock-text);
                font-size: 1.05rem;
                font-variant-numeric: tabular-nums;
                line-height: 1.25;
            }

            .stock-selected__metric small,
            .stock-selected__history small {
                align-self: end;
                color: #8a94ad;
                font-size: 0.66rem;
                line-height: 1.35;
            }

            .stock-selected__history {
                border-top: 1px solid rgba(205, 220, 245, 0.75);
                background: rgba(238, 244, 255, 0.55);
                padding-top: 1rem;
            }

            .stock-selected__history > div {
                border: 0;
                background: transparent;
                padding: 0.25rem 0.5rem;
            }

            .stock-selected__history strong {
                grid-column: 1 / -1;
            }

            .stock-selected__history strong.is-inbound {
                color: #05734d;
            }

            .stock-selected__history strong.is-outbound {
                color: #b51f4b;
            }

            .stock-selected__history strong.is-date {
                font-size: 0.83rem;
            }

            .stock-selected__history small {
                grid-column: 1 / -1;
            }

            .stock-kpis {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.9rem;
            }

            .stock-kpi {
                position: relative;
                display: grid;
                min-width: 0;
                gap: 0.3rem;
                overflow: hidden;
                border: 1px solid var(--stock-border);
                border-radius: 1rem;
                background: #fff;
                padding: 1rem 1.05rem;
                box-shadow: 0 8px 22px rgba(15, 34, 97, 0.045);
            }

            .stock-kpi::before {
                position: absolute;
                inset: 0 auto 0 0;
                width: 3px;
                background: #7ea8f0;
                content: '';
            }

            .stock-kpi--alert::before,
            .stock-kpi--outbound::before {
                background: #e53e6b;
            }

            .stock-kpi--inbound::before {
                background: #00c97b;
            }

            .stock-kpi > span {
                color: var(--stock-muted);
                font-size: 0.7rem;
                font-weight: 750;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .stock-kpi > strong {
                color: var(--stock-text);
                font-size: 1.55rem;
                font-variant-numeric: tabular-nums;
                line-height: 1.1;
            }

            .stock-kpi > small {
                color: #9098b0;
                font-size: 0.72rem;
            }

            .stock-table-wrap {
                overflow-x: auto;
            }

            .stock-table {
                width: 100%;
                min-width: 760px;
                border-collapse: collapse;
            }

            .stock-table th {
                border-bottom: 1px solid rgba(212, 216, 230, 0.72);
                background: #f7f9fc;
                padding: 0.7rem 1rem;
                color: #6b7694;
                font-size: 0.66rem;
                font-weight: 750;
                letter-spacing: 0.06em;
                text-align: left;
                text-transform: uppercase;
            }

            .stock-table th:not(:first-child),
            .stock-table td:not(:first-child) {
                text-align: right;
            }

            .stock-table td {
                border-bottom: 1px solid rgba(232, 235, 242, 0.92);
                padding: 0.85rem 1rem;
                color: #2d3756;
                font-size: 0.82rem;
                vertical-align: middle;
            }

            .stock-table tbody tr:last-child td {
                border-bottom: 0;
            }

            .stock-table tbody tr:hover td {
                background: rgba(244, 247, 252, 0.78);
            }

            .stock-item-name {
                display: grid;
                justify-items: start;
                gap: 0.2rem;
                min-width: 13rem;
            }

            .stock-item-name strong {
                color: var(--stock-text);
                font-size: 0.86rem;
                line-height: 1.35;
            }

            .stock-item-name small,
            .stock-number small {
                color: #9098b0;
                font-size: 0.68rem;
            }

            .stock-kind,
            .stock-movement,
            .stock-status {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                border-radius: 999px;
                padding: 0.25rem 0.5rem;
                font-size: 0.63rem;
                font-weight: 750;
                line-height: 1;
                white-space: nowrap;
            }

            .stock-kind--produto {
                background: #eaf1fd;
                color: #1e45a8;
            }

            .stock-kind--insumo {
                background: #f1edff;
                color: #6d4bb4;
            }

            .stock-number {
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
            }

            .stock-number--strong {
                color: var(--stock-text) !important;
                font-weight: 750;
            }

            .stock-muted {
                color: #9098b0;
                font-size: 0.73rem;
            }

            .stock-history-link {
                border: 1px solid #d5e1f6;
                border-radius: 999px;
                background: #f4f8ff;
                padding: 0.4rem 0.65rem;
                color: #2452b3;
                font-size: 0.68rem;
                font-weight: 750;
                white-space: nowrap;
                cursor: pointer;
            }

            .stock-history-link:hover,
            .stock-history-link.is-active {
                border-color: #7fa3e3;
                background: #17368d;
                color: #fff;
            }

            .stock-status--ok {
                background: #e8fbf3;
                color: #05734d;
            }

            .stock-status--alert,
            .stock-status--reversed {
                background: #fff0f3;
                color: #b51f4b;
            }

            .stock-status--reversal {
                background: #fff7e3;
                color: #9a6200;
            }

            .stock-timeline {
                display: grid;
                padding: 0.35rem 1.25rem 0.8rem;
            }

            .stock-event {
                position: relative;
                display: grid;
                grid-template-columns: 1.6rem minmax(0, 1fr);
                min-width: 0;
            }

            .stock-event__axis {
                position: relative;
                display: flex;
                justify-content: center;
            }

            .stock-event__axis::after {
                position: absolute;
                inset: 1.7rem auto -1.1rem;
                width: 1px;
                background: #dfe5ef;
                content: '';
            }

            .stock-event:last-child .stock-event__axis::after {
                display: none;
            }

            .stock-event__axis span {
                position: relative;
                z-index: 1;
                width: 0.7rem;
                height: 0.7rem;
                margin-top: 1.25rem;
                border: 2px solid #fff;
                border-radius: 999px;
                background: #9098b0;
                box-shadow: 0 0 0 2px #d4d8e6;
            }

            .stock-event--inbound .stock-event__axis span {
                background: #00a867;
                box-shadow: 0 0 0 2px #a9efd2;
            }

            .stock-event--outbound .stock-event__axis span {
                background: #dc3564;
                box-shadow: 0 0 0 2px #ffc2d2;
            }

            .stock-event__body {
                min-width: 0;
                margin: 0.65rem 0 0.55rem 0.55rem;
                border: 1px solid rgba(212, 216, 230, 0.78);
                border-radius: 1rem;
                background: #fff;
                padding: 1rem;
            }

            .stock-event--sale .stock-event__body {
                border-color: rgba(90, 139, 230, 0.46);
                box-shadow: inset 3px 0 0 #3a6dd6;
            }

            .stock-event__top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
            }

            .stock-event__identity {
                min-width: 0;
            }

            .stock-event__badges {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }

            .stock-movement--inbound {
                background: #e8fbf3;
                color: #05734d;
            }

            .stock-movement--outbound {
                background: #fff0f3;
                color: #b51f4b;
            }

            .stock-movement--neutral {
                background: #f1f3f7;
                color: #5d6680;
            }

            .stock-event__identity h4 {
                margin: 0.55rem 0 0;
                color: var(--stock-text);
                font-size: 0.92rem;
                font-weight: 750;
                line-height: 1.35;
            }

            .stock-event__identity p {
                margin: 0.2rem 0 0;
                color: #6b7694;
                font-size: 0.72rem;
            }

            .stock-impact {
                display: grid;
                flex: 0 0 auto;
                grid-template-columns: auto auto;
                align-items: baseline;
                gap: 0.2rem 0.4rem;
                min-width: 7.25rem;
                border-radius: 0.8rem;
                padding: 0.65rem 0.75rem;
                text-align: right;
            }

            .stock-impact span {
                grid-column: 1 / -1;
                color: currentColor;
                font-size: 0.62rem;
                font-weight: 750;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .stock-impact strong {
                font-size: 1rem;
                font-variant-numeric: tabular-nums;
            }

            .stock-impact small {
                font-size: 0.65rem;
            }

            .stock-impact--inbound {
                background: #e8fbf3;
                color: #05734d;
            }

            .stock-impact--outbound {
                background: #fff0f3;
                color: #b51f4b;
            }

            .stock-impact--neutral {
                background: #f1f3f7;
                color: #5d6680;
            }

            .stock-origin {
                display: grid;
                gap: 0.1rem;
                margin-top: 0.85rem;
                border-radius: 0.75rem;
                background: #f7f9fc;
                padding: 0.65rem 0.75rem;
            }

            .stock-origin--sale {
                border: 1px solid #d0e0fa;
                background: #eef4ff;
            }

            .stock-origin span,
            .stock-event__details dt {
                color: #6b7694;
                font-size: 0.62rem;
                font-weight: 750;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .stock-origin strong {
                color: #17368d;
                font-size: 0.8rem;
            }

            .stock-origin small {
                color: #6b7694;
                font-size: 0.68rem;
            }

            .stock-event__details {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.75rem;
                margin: 0.85rem 0 0;
            }

            .stock-event__details > div {
                min-width: 0;
            }

            .stock-event__details dd {
                overflow-wrap: anywhere;
                margin: 0.2rem 0 0;
                color: #2d3756;
                font-size: 0.75rem;
                font-weight: 650;
                line-height: 1.4;
            }

            .stock-event__note {
                margin-top: 0.8rem;
                border-top: 1px solid #e8ebf2;
                padding-top: 0.7rem;
            }

            .stock-event__note strong {
                color: #2d3756;
                font-size: 0.75rem;
            }

            .stock-event__note p {
                margin: 0.2rem 0 0;
                color: #6b7694;
                font-size: 0.72rem;
                line-height: 1.5;
            }

            .stock-empty {
                padding: 2rem 1rem !important;
                color: #6b7694 !important;
                font-size: 0.8rem !important;
                text-align: center !important;
            }

            .stock-empty--timeline {
                margin: 0.8rem 0;
                border: 1px dashed #d4d8e6;
                border-radius: 0.9rem;
                background: #fafbfd;
            }

            .stock-pagination {
                border-top: 1px solid rgba(212, 216, 230, 0.68);
                padding: 0.9rem 1.1rem;
                background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
            }

            .stock-pagination .fi-pagination {
                width: 100%;
                min-width: 0;
                gap: 0.75rem;
            }

            .stock-pagination .fi-pagination-overview {
                color: #6b7694;
                font-size: 0.78rem;
                font-weight: 650;
            }

            .stock-pagination .fi-pagination-items {
                overflow: hidden;
                border: 1px solid #dce3f2;
                border-radius: 0.75rem;
                background: #fff;
                box-shadow: 0 5px 15px rgba(15, 34, 97, 0.06);
            }

            .stock-pagination .fi-pagination-item {
                border-color: #e6eaf3;
            }

            .stock-pagination .fi-pagination-item-btn {
                min-width: 2.35rem;
                min-height: 2.35rem;
                align-items: center;
                justify-content: center;
            }

            .stock-pagination .fi-pagination-item-icon {
                width: 1rem;
                height: 1rem;
            }

            .stock-pagination .fi-pagination-item.fi-active .fi-pagination-item-btn {
                background: #17368d;
            }

            .stock-pagination .fi-pagination-item.fi-active .fi-pagination-item-label {
                color: #fff;
            }

            .stock-pagination .fi-pagination-previous-btn,
            .stock-pagination .fi-pagination-next-btn {
                min-height: 2.35rem;
                border-color: #dce3f2;
                border-radius: 0.7rem;
                background: #fff;
                color: #17368d;
                box-shadow: 0 4px 12px rgba(15, 34, 97, 0.05);
            }

            @media (max-width: 1100px) {
                .stock-filter-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .stock-kpis {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .stock-selected__grid,
                .stock-selected__history {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .stock-event__details {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 680px) {
                .stock-panel__header,
                .stock-event__top {
                    flex-direction: column;
                }

                .stock-filter-grid,
                .stock-kpis,
                .stock-selected__grid,
                .stock-selected__history,
                .stock-event__details {
                    grid-template-columns: 1fr;
                }

                .stock-field--wide {
                    grid-column: auto;
                }

                .stock-filter-group-title {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 0.2rem;
                }

                .stock-selected__header {
                    flex-direction: column;
                }

                .stock-panel__tools {
                    width: 100%;
                    justify-content: flex-start;
                }

                .stock-export {
                    flex: 1 1 100%;
                }

                .stock-impact {
                    width: 100%;
                    grid-template-columns: 1fr auto;
                    text-align: left;
                }

                .stock-impact span {
                    grid-column: auto;
                }

                .stock-event {
                    grid-template-columns: 1.15rem minmax(0, 1fr);
                }

                .stock-event__body {
                    margin-left: 0.25rem;
                    padding: 0.85rem;
                }

                .stock-pagination {
                    padding: 0.75rem;
                }

                .stock-pagination .fi-pagination {
                    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                    gap: 0.5rem;
                }

                .stock-pagination .fi-pagination-previous-btn,
                .stock-pagination .fi-pagination-next-btn {
                    width: 100%;
                    padding-inline: 0.65rem;
                }
            }
        </style>
    @endonce
</x-filament-panels::page>
