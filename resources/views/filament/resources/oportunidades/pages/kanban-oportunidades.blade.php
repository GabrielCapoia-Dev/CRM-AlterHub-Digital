<x-filament-panels::page>
    @php
        $activeView = $viewMode === 'kanban' ? 'kanban' : 'list';
        $columns = $activeView === 'kanban' ? $this->getBoardColumns() : [];
        $listRows = $activeView === 'list' ? collect($this->getListRows()) : collect();
        $visibleRows = $activeView === 'kanban'
            ? collect($columns)->flatMap(fn (array $column) => $column['cards'])
            : $listRows;
        $owners = $this->getOwnerOptions();
        $segments = $this->getSegmentOptions();
        $stages = $this->getStageOptions();
        $products = $this->getProductOptions();
        $selectedOpportunity = $this->getSelectedOpportunity();
        $pendingStageName = data_get(collect($stages)->firstWhere('id', $pendingMoveStageId), 'nome', 'Encerramento');
        $canEditOpportunity = $selectedOpportunity
            ? auth()->user()?->can('update', $selectedOpportunity)
            : auth()->user()?->can('create', \App\Models\Oportunidade::class);
        $hasFilters = filled($search)
            || filled($temperatureFilter)
            || filled($lastInteractionDays)
            || filled($ownerFilter)
            || filled($segmentFilter);
        $visibleCount = $visibleRows->count();
        $visibleTotal = (float) $visibleRows->sum(fn (array $card) => (float) ($card['value'] ?? 0));
        $visibleTotalFormatted = 'R$ ' . number_format($visibleTotal, 2, ',', '.');
    @endphp

    <div
        x-data="crmKanbanBoard()"
        class="crm-kanban-page"
        @pointerup.window="releaseCard()"
        @pointercancel.window="releaseCard()"
    >
        <div class="crm-kanban-loading-overlay" wire:loading.flex role="status" aria-live="polite" aria-busy="true">
            <div class="crm-kanban-loading-backdrop"></div>

            <div class="crm-kanban-loading-panel">
                <div class="crm-kanban-loading-spinner" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="crm-kanban-loading-copy">
                    <p class="crm-kanban-eyebrow">Processando</p>
                    <strong>Atualizando quadro comercial</strong>
                    <p>Estamos sincronizando o CRM e bloqueando novas interacoes ate a operacao concluir.</p>
                </div>
            </div>
        </div>

        <section class="crm-kanban-shell">
            <header class="crm-kanban-hero crm-kanban-hero-compact crm-kanban-toolbar">
                <div class="crm-view-switcher" role="group" aria-label="Visualizacao das oportunidades">
                    <button
                        type="button"
                        class="{{ $activeView === 'list' ? 'is-active' : '' }}"
                        aria-pressed="{{ $activeView === 'list' ? 'true' : 'false' }}"
                        wire:click="setViewMode('list')"
                    >
                        Lista
                    </button>

                    <button
                        type="button"
                        class="{{ $activeView === 'kanban' ? 'is-active' : '' }}"
                        aria-pressed="{{ $activeView === 'kanban' ? 'true' : 'false' }}"
                        wire:click="setViewMode('kanban')"
                    >
                        Kanban
                    </button>
                </div>

                <div class="crm-kanban-hero-actions">
                    @can('create', \App\Models\Oportunidade::class)
                        <button type="button" class="crm-btn crm-btn-primary" wire:click="openCreateDrawer">
                            Nova oportunidade
                        </button>
                    @endcan
                </div>
            </header>

            <section class="crm-kanban-filters">
                <div class="crm-kanban-filter-grid">
                    <label class="crm-field crm-field-search">
                        <span>Busca</span>
                        <input
                            type="search"
                            placeholder="Cliente, contato, e-mail ou oportunidade"
                            wire:model.live.debounce.400ms="search"
                        >
                    </label>

                    <label class="crm-field">
                        <span>Responsavel</span>
                        <select wire:model.live="ownerFilter">
                            <option value="">Todos</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner['id'] }}">{{ $owner['nome'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="crm-field">
                        <span>Temperatura</span>
                        <select wire:model.live="temperatureFilter">
                            <option value="">Todas</option>
                            @foreach (\App\Models\Oportunidade::temperaturaOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="crm-field">
                        <span>Ultima interacao</span>
                        <select wire:model.live="lastInteractionDays">
                            <option value="">Qualquer data</option>
                            <option value="7">Ate 7 dias</option>
                            <option value="30">Ate 30 dias</option>
                            <option value="90">Ate 90 dias</option>
                        </select>
                    </label>
                </div>

                <div class="crm-kanban-filter-bar">
                    <div class="crm-kanban-filter-pills">
                        <button
                            type="button"
                            class="crm-filter-pill {{ blank($segmentFilter) ? 'is-active' : '' }}"
                            wire:click="setSegmentFilter"
                        >
                            Todos os segmentos
                        </button>

                        @foreach ($segments as $segment)
                            <button
                                type="button"
                                class="crm-filter-pill {{ (int) $segmentFilter === $segment['id'] ? 'is-active' : '' }}"
                                wire:click="setSegmentFilter({{ $segment['id'] }})"
                            >
                                {{ $segment['nome'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="crm-kanban-filter-meta">
                        <span>{{ $visibleCount }} oportunidade(s) filtrada(s) - {{ $visibleTotalFormatted }}</span>

                        @if ($hasFilters)
                            <button type="button" class="crm-filter-clear" wire:click="resetFilters">
                                Limpar filtros
                            </button>
                        @endif
                    </div>
                </div>
            </section>

            @if ($activeView === 'list')
                <section class="crm-opportunity-list" aria-label="Lista de oportunidades">
                    @forelse ($listRows as $card)
                        <article
                            class="crm-opportunity-row"
                            style="--crm-stage-color: {{ $card['stage_color'] }}"
                            wire:key="list-card-{{ $card['id'] }}"
                        >
                            <div
                                class="crm-opportunity-row-main"
                                tabindex="0"
                                role="button"
                                aria-label="Abrir oportunidade {{ $card['title'] }}"
                                @click="openCard($event, {{ $card['id'] }}, $wire)"
                                @keydown.enter.prevent="openCard($event, {{ $card['id'] }}, $wire)"
                                @keydown.space.prevent="openCard($event, {{ $card['id'] }}, $wire)"
                            >
                                <div class="crm-opportunity-title-line">
                                    <span class="crm-kanban-stage-dot"></span>

                                    <div>
                                        <h3>{{ $card['title'] }}</h3>
                                        <p>{{ $card['company'] ?: 'Cliente nao informado' }}</p>
                                    </div>
                                </div>

                                <div class="crm-opportunity-row-meta">
                                    <span>{{ $card['contact'] ?: 'Sem contato' }}</span>
                                    <span>{{ $card['email'] ?: 'Sem e-mail' }}</span>
                                    <span>{{ $card['segment'] ?: 'Sem segmento' }}</span>
                                </div>

                                @if (count($card['products']))
                                    <div class="crm-kanban-chip-row">
                                        @foreach ($card['products'] as $product)
                                            <span class="crm-kanban-chip">{{ $product }}</span>
                                        @endforeach

                                        @if ($card['extra_products_count'] > 0)
                                            <span class="crm-kanban-chip">+{{ $card['extra_products_count'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="crm-opportunity-row-metrics">
                                <div>
                                    <span class="crm-kanban-card-label">Valor estimado</span>
                                    <strong>{{ $card['value_formatted'] ?? 'Sem valor' }}</strong>
                                </div>

                                <div>
                                    <span class="crm-kanban-card-label">Ultima interacao</span>
                                    <strong>{{ $card['last_interaction_label'] }}</strong>
                                </div>
                            </div>

                            <div class="crm-opportunity-row-stage">
                                <span class="crm-kanban-card-label">Etapa</span>

                                <div class="crm-opportunity-stage-stack">
                                    <span class="crm-kanban-status-badge crm-kanban-status-{{ \Illuminate\Support\Str::slug($card['stage_slug'] ?: $card['stage_name']) }}">
                                        {{ $card['stage_name'] }}
                                    </span>

                                    <select
                                        class="crm-list-stage-select"
                                        aria-label="Mover oportunidade {{ $card['title'] }}"
                                        @click.stop
                                        @keydown.stop
                                        @change.stop="$wire.moveOpportunity({{ $card['id'] }}, Number($event.target.value))"
                                        @disabled(! $card['can_update'])
                                    >
                                        @foreach ($stages as $stage)
                                            <option value="{{ $stage['id'] }}" @selected((int) $card['stage_id'] === $stage['id'])>
                                                {{ $stage['nome'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <footer class="crm-opportunity-row-footer">
                                <div class="crm-kanban-owner">
                                    <span>{{ $card['owner_initials'] }}</span>

                                    <div>
                                        <strong>{{ $card['owner'] ?: 'Sem responsavel' }}</strong>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="crm-btn crm-btn-secondary crm-list-open-btn"
                                    @click.stop="openCard($event, {{ $card['id'] }}, $wire)"
                                >
                                    Abrir
                                </button>
                            </footer>
                        </article>
                    @empty
                        <div class="crm-opportunity-empty">
                            Nenhuma oportunidade encontrada com os filtros atuais.
                        </div>
                    @endforelse
                </section>
            @elseif (count($columns))
                <section class="crm-kanban-board" aria-label="Quadro Kanban de oportunidades">
                    @foreach ($columns as $column)
                        <article
                            class="crm-kanban-stage {{ $column['fechamento'] ? 'is-closing' : '' }}"
                            style="--crm-stage-color: {{ $column['cor'] }}"
                            wire:key="stage-{{ $column['id'] }}"
                        >
                            <header class="crm-kanban-stage-header">
                                <div class="crm-kanban-stage-title">
                                    <span class="crm-kanban-stage-dot"></span>

                                    <div>
                                        <h3>{{ $column['nome'] }}</h3>
                                        <p>{{ $column['count'] }} oportunidade(s)</p>
                                    </div>
                                </div>

                                <div class="crm-kanban-stage-summary">
                                    <strong>{{ $column['sum_formatted'] }}</strong>

                                    @if ($column['fechamento'])
                                        <span class="crm-kanban-stage-flag">Encerramento</span>
                                    @endif
                                </div>
                            </header>

                            <div
                                class="crm-kanban-stage-body"
                                @dragover.prevent="dragOver($event)"
                                @dragleave="dragLeave($event)"
                                @drop.prevent="drop($event, {{ $column['id'] }}, $wire)"
                            >
                                @forelse ($column['cards'] as $card)
                                    <article
                                        class="crm-kanban-card"
                                        wire:key="card-{{ $card['id'] }}"
                                        draggable="true"
                                        tabindex="0"
                                        role="button"
                                        aria-label="Abrir oportunidade {{ $card['title'] }}"
                                        @pointerdown="primeCard($event, {{ $card['id'] }})"
                                        @dragstart="startDrag($event, {{ $card['id'] }})"
                                        @dragend="endDrag($event)"
                                        @click="openCard($event, {{ $card['id'] }}, $wire)"
                                        @keydown.enter.prevent="openCard($event, {{ $card['id'] }}, $wire)"
                                        @keydown.space.prevent="openCard($event, {{ $card['id'] }}, $wire)"
                                        :class="cardClasses({{ $card['id'] }})"
                                    >
                                        <div class="crm-kanban-card-top">
                                            <div class="crm-kanban-card-heading">
                                                <h4>{{ $card['title'] }}</h4>
                                                <p class="crm-kanban-card-company">{{ $card['company'] }}</p>
                                            </div>

                                            <div class="crm-kanban-card-badges">
                                                <span class="crm-kanban-status-badge crm-kanban-status-{{ \Illuminate\Support\Str::slug($column['slug'] ?: $column['nome']) }}">
                                                    {{ $column['nome'] }}
                                                </span>
                                                <span class="crm-kanban-temp crm-kanban-temp-{{ $card['temperature'] }}">
                                                    {{ $card['temperature_label'] }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="crm-kanban-card-grid">
                                            <div>
                                                <span class="crm-kanban-card-label">Valor estimado</span>
                                                <strong>{{ $card['value_formatted'] ?? 'Sem valor' }}</strong>
                                            </div>

                                            <div>
                                                <span class="crm-kanban-card-label">Ultima interacao</span>
                                                <strong>{{ $card['last_interaction_label'] }}</strong>
                                            </div>
                                        </div>

                                        <footer class="crm-kanban-card-footer">
                                            <div class="crm-kanban-owner">
                                                <span>{{ $card['owner_initials'] }}</span>

                                                <div>
                                                    <strong>{{ $card['owner'] ?: 'Sem responsavel' }}</strong>
                                                </div>
                                            </div>

                                            <div class="crm-kanban-card-grip" aria-hidden="true">
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                            </div>
                                        </footer>
                                    </article>
                                @empty
                                    <div class="crm-kanban-empty">
                                        Nenhuma oportunidade nesta etapa com os filtros atuais.
                                    </div>
                                @endforelse
                            </div>

                            @can('create', \App\Models\Oportunidade::class)
                                <button
                                    type="button"
                                    class="crm-kanban-stage-create"
                                    wire:click="openCreateDrawer({{ $column['id'] }})"
                                >
                                    + Nova oportunidade aqui
                                </button>
                            @endcan
                        </article>
                    @endforeach
                </section>
            @else
                <section class="crm-kanban-zero">
                    <p class="crm-kanban-eyebrow">Visualizacao Kanban</p>
                    <h3>Nenhuma etapa configurada</h3>
                    <p>Crie as etapas do funil para comecar a trabalhar o quadro Kanban.</p>

                    @can('viewAny', \App\Models\Etapa::class)
                        <a href="{{ \App\Filament\Resources\Etapas\EtapaResource::getUrl() }}" class="crm-btn crm-btn-primary">
                            Abrir etapas
                        </a>
                    @endcan
                </section>
            @endif
        </section>

        @if ($closingReasonModalOpen)
            <div class="crm-modal-root" role="dialog" aria-modal="true" aria-labelledby="crm-close-modal-title">
                <div class="crm-modal-backdrop" wire:click="cancelPendingMove"></div>

                <div class="crm-modal-panel">
                    <div class="crm-modal-copy">
                        <p class="crm-kanban-eyebrow">Fechamento obrigatorio</p>
                        <h3 id="crm-close-modal-title">Motivo do fechamento</h3>
                        <p>
                            Para mover esta oportunidade para <strong>{{ $pendingStageName }}</strong>, informe o motivo do encerramento.
                        </p>
                    </div>

                    <label class="crm-field">
                        <span>Motivo</span>
                        <textarea rows="4" wire:model.defer="pendingMoveReason" placeholder="Ex.: proposta aceita, prazo acordado ou perda por preco."></textarea>
                        @error('pendingMoveReason')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <div class="crm-modal-actions">
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="cancelPendingMove">
                            Cancelar
                        </button>

                        <button type="button" class="crm-btn crm-btn-primary" wire:click="confirmPendingStageMove">
                            Confirmar movimentacao
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if ($saleConfirmationModalOpen)
            <div class="crm-modal-root" role="dialog" aria-modal="true" aria-labelledby="crm-sale-modal-title">
                <div class="crm-modal-backdrop" wire:click="closeSaleConfirmation"></div>

                <div class="crm-modal-panel crm-sale-modal-panel">
                    <div class="crm-modal-copy">
                        <p class="crm-kanban-eyebrow">Confirmacao de venda</p>
                        <h3 id="crm-sale-modal-title">Transformar oportunidade em venda</h3>
                        <p>
                            Confirme os dados abaixo. A venda so sera registrada se todos os produtos tiverem estoque suficiente.
                        </p>
                    </div>

                    <section class="crm-sale-summary">
                        <div>
                            <span>Oportunidade</span>
                            <strong>{{ data_get($salePreview, 'opportunity.titulo', '-') }}</strong>
                        </div>

                        <div>
                            <span>Cliente</span>
                            <strong>{{ data_get($salePreview, 'opportunity.cliente', '-') }}</strong>
                        </div>

                        <div>
                            <span>Responsavel</span>
                            <strong>{{ data_get($salePreview, 'opportunity.responsavel', '-') }}</strong>
                        </div>
                    </section>

                    @if (! empty($salePreview['errors'] ?? []))
                        <div class="crm-sale-alert">
                            @foreach ($salePreview['errors'] as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="crm-sale-items">
                        @forelse (($salePreview['items'] ?? []) as $item)
                            <article class="crm-sale-item {{ $item['ok'] ? '' : 'has-error' }}">
                                <div>
                                    <h4>{{ $item['produto_nome'] }}</h4>
                                    <p>{{ $item['produto_codigo'] ?: 'Sem codigo' }}</p>
                                </div>

                                <div>
                                    <span>Qtd.</span>
                                    <strong>{{ number_format((float) $item['quantidade'], 4, ',', '.') }} {{ $item['unidade'] }}</strong>
                                </div>

                                <div>
                                    <span>Estoque</span>
                                    <strong>{{ number_format((float) $item['estoque_atual'], 4, ',', '.') }} {{ $item['unidade'] }}</strong>
                                </div>

                                <div>
                                    <span>Preco</span>
                                    <strong>R$ {{ number_format((float) $item['preco_unitario'], 2, ',', '.') }}</strong>
                                    <small>{{ $item['preco_origem'] === 'negociado' ? 'Negociado' : 'Tabela' }}</small>
                                </div>

                                <div>
                                    <span>Total</span>
                                    <strong>R$ {{ number_format((float) $item['subtotal'], 2, ',', '.') }}</strong>
                                </div>
                            </article>
                        @empty
                            <div class="crm-drawer-empty">Nenhum produto disponivel para confirmar.</div>
                        @endforelse
                    </div>

                    <section class="crm-sale-totals">
                        <div>
                            <span>Itens</span>
                            <strong>{{ data_get($salePreview, 'totals.itens_count', 0) }}</strong>
                        </div>

                        <div>
                            <span>Quantidade total</span>
                            <strong>{{ number_format((float) data_get($salePreview, 'totals.quantidade_total', 0), 4, ',', '.') }}</strong>
                        </div>

                        <div>
                            <span>Receita bruta</span>
                            <strong>R$ {{ number_format((float) data_get($salePreview, 'totals.receita_bruta', 0), 2, ',', '.') }}</strong>
                        </div>
                    </section>

                    <div class="crm-modal-actions">
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="closeSaleConfirmation">
                            Cancelar
                        </button>

                        <button
                            type="button"
                            class="crm-btn crm-btn-primary"
                            wire:click="confirmOpportunitySale"
                            @disabled(! data_get($salePreview, 'can_convert', false))
                        >
                            Confirmar venda
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if ($drawerOpen)
            <div class="crm-drawer-root">
                <button type="button" class="crm-drawer-backdrop" wire:click="closeDrawer" aria-label="Fechar painel lateral"></button>

                <aside class="crm-drawer-panel" aria-label="Detalhes da oportunidade">
                    <header class="crm-drawer-header">
                        <div class="crm-drawer-header-copy">
                            <p class="crm-kanban-eyebrow">{{ $drawerMode === 'create' ? 'Nova oportunidade' : 'Oportunidade selecionada' }}</p>
                            <h3>{{ $selectedOpportunity?->titulo ?? 'Nova oportunidade' }}</h3>
                            <p>
                                {{ $selectedOpportunity?->cliente?->razao_social ?? ($opportunityForm['client_razao_social'] ?: 'Preencha os dados principais para comecar.') }}
                            </p>
                        </div>

                        <div class="crm-drawer-actions">
                            @if ($selectedOpportunity)
                                @if ($selectedOpportunity->vendaOperacaoPedido)
                                    <a href="{{ \App\Filament\Resources\VendasOperacao\VendaOperacaoResource::getUrl() }}" class="crm-btn crm-btn-secondary">
                                        Venda {{ $selectedOpportunity->vendaOperacaoPedido->codigo }}
                                    </a>
                                @elseif (auth()->user()?->can('update', $selectedOpportunity) && auth()->user()?->can('create', \App\Models\VendaOperacao::class))
                                    <button type="button" class="crm-btn crm-btn-primary" wire:click="openSaleConfirmation">
                                        Transformar em venda
                                    </button>
                                @endif

                                <a href="{{ \App\Filament\Resources\Oportunidades\OportunidadeResource::getUrl('view', ['record' => $selectedOpportunity]) }}" class="crm-btn crm-btn-secondary">
                                    Visualizar
                                </a>

                                @can('update', $selectedOpportunity)
                                    <a href="{{ \App\Filament\Resources\Oportunidades\OportunidadeResource::getUrl('edit', ['record' => $selectedOpportunity]) }}" class="crm-btn crm-btn-secondary">
                                        Edicao completa
                                    </a>
                                @endcan
                            @endif

                            <button type="button" class="crm-icon-btn" wire:click="closeDrawer" aria-label="Fechar painel">
                                ×
                            </button>
                        </div>
                    </header>

                    <nav class="crm-drawer-tabs" aria-label="Abas da oportunidade">
                        <button type="button" class="{{ $activeDrawerTab === 'summary' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'summary')">
                            Resumo
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'products' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'products')" @disabled(! $selectedOpportunity)>
                            Produtos
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'interactions' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'interactions')" @disabled(! $selectedOpportunity)>
                            Interacoes
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'tasks' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'tasks')" @disabled(! $selectedOpportunity)>
                            Tarefas
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'movements' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'movements')" @disabled(! $selectedOpportunity)>
                            Movimentacoes
                        </button>
                    </nav>

                    <div class="crm-drawer-body">
                        @if ($activeDrawerTab === 'summary')
                            @include('filament.resources.oportunidades.pages.partials.summary')
                        @elseif ($activeDrawerTab === 'products')
                            @include('filament.resources.oportunidades.pages.partials.products')
                        @elseif ($activeDrawerTab === 'interactions')
                            @include('filament.resources.oportunidades.pages.partials.interactions')
                        @elseif ($activeDrawerTab === 'tasks')
                            @include('filament.resources.oportunidades.pages.partials.tasks')
                        @else
                            @include('filament.resources.oportunidades.pages.partials.movements')
                        @endif
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-filament-panels::page>
