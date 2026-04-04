<x-filament-panels::page>
    @php
        $columns = $this->getBoardColumns();
        $owners = $this->getOwnerOptions();
        $segments = $this->getSegmentOptions();
        $stages = $this->getStageOptions();
        $clients = $this->getClientOptions();
        $products = $this->getProductOptions();
        $selectedOpportunity = $this->getSelectedOpportunity();
        $selectedClientSegment = $this->getSelectedClientSegmentName();
        $pendingStageName = data_get(collect($stages)->firstWhere('id', $pendingMoveStageId), 'nome', 'Encerramento');
        $canEditOpportunity = $selectedOpportunity
            ? auth()->user()?->can('update', $selectedOpportunity)
            : auth()->user()?->can('create', \App\Models\Oportunidade::class);
        $hasFilters = filled($search)
            || filled($temperatureFilter)
            || filled($lastInteractionDays)
            || filled($ownerFilter)
            || filled($segmentFilter);
    @endphp

    <div x-data="crmKanbanBoard()" class="crm-kanban-page">
        <section class="crm-kanban-shell">
            <header class="crm-kanban-toolbar">
                <div class="crm-kanban-toolbar-copy">
                    <p class="crm-kanban-eyebrow">CRM operacional</p>
                    <h2>Pipeline comercial</h2>
                    <p>
                        Arraste entre etapas, acompanhe valores por coluna e trabalhe o contexto da negociação sem sair do quadro.
                    </p>
                </div>

                <div class="crm-kanban-toolbar-actions">
                    <a href="{{ $this->getListUrl() }}" class="crm-btn crm-btn-secondary">
                        Lista de apoio
                    </a>

                    @can('create', \App\Models\Oportunidade::class)
                        <button type="button" class="crm-btn crm-btn-primary" wire:click="openCreateDrawer">
                            Nova oportunidade
                        </button>
                    @endcan
                </div>
            </header>

            <section class="crm-kanban-filters">
                <div class="crm-kanban-filter-grid">
                    <label class="crm-field">
                        <span>Busca</span>
                        <input
                            type="search"
                            placeholder="Cliente, contato, e-mail ou oportunidade"
                            wire:model.live.debounce.400ms="search"
                        >
                    </label>

                    <label class="crm-field">
                        <span>Responsável</span>
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
                        <span>Última interação</span>
                        <select wire:model.live="lastInteractionDays">
                            <option value="">Qualquer data</option>
                            <option value="7">Até 7 dias</option>
                            <option value="30">Até 30 dias</option>
                            <option value="90">Até 90 dias</option>
                        </select>
                    </label>
                </div>

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

                    @if ($hasFilters)
                        <button type="button" class="crm-filter-clear" wire:click="resetFilters">
                            Limpar filtros
                        </button>
                    @endif
                </div>
            </section>

            @if (count($columns))
                <section class="crm-kanban-board" aria-label="Quadro Kanban de oportunidades">
                    @foreach ($columns as $column)
                        <article class="crm-kanban-stage" wire:key="stage-{{ $column['id'] }}">
                            <header class="crm-kanban-stage-header">
                                <div class="crm-kanban-stage-title">
                                    <span class="crm-kanban-stage-dot" style="--crm-stage-color: {{ $column['cor'] }}"></span>
                                    <div>
                                        <h3>{{ $column['nome'] }}</h3>
                                        <p>{{ $column['count'] }} oportunidade(s)</p>
                                    </div>
                                </div>

                                <strong>{{ $column['sum_formatted'] }}</strong>
                            </header>

                            <div
                                class="crm-kanban-stage-body"
                                @dragover.prevent="dragOver($event)"
                                @dragleave="dragLeave($event)"
                                @drop.prevent="drop($event, {{ $column['id'] }}, $wire)"
                            >
                                @forelse ($column['cards'] as $card)
                                    <article class="crm-kanban-card" wire:key="card-{{ $card['id'] }}">
                                        <div class="crm-kanban-card-head">
                                            <span class="crm-kanban-badge">{{ $card['segment'] ?? 'Sem segmento' }}</span>

                                            <button
                                                type="button"
                                                class="crm-kanban-drag-handle"
                                                draggable="true"
                                                @dragstart="startDrag($event, {{ $card['id'] }})"
                                                @dragend="endDrag($event)"
                                                title="Arrastar oportunidade"
                                            >
                                                <span></span>
                                                <span></span>
                                            </button>
                                        </div>

                                        <button type="button" class="crm-kanban-card-body" wire:click="openDrawer({{ $card['id'] }})">
                                            <h4>{{ $card['title'] }}</h4>
                                            <p class="crm-kanban-card-company">{{ $card['company'] }}</p>

                                            @if ($card['contact'] || $card['email'])
                                                <p class="crm-kanban-card-meta">
                                                    {{ $card['contact'] ?: 'Sem contato' }}
                                                    @if ($card['email'])
                                                        · {{ $card['email'] }}
                                                    @endif
                                                </p>
                                            @endif

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

                                            <div class="crm-kanban-card-grid">
                                                <div>
                                                    <span class="crm-kanban-card-label">Temperatura</span>
                                                    <strong>{{ $card['temperature_label'] }}</strong>
                                                </div>

                                                <div>
                                                    <span class="crm-kanban-card-label">Valor</span>
                                                    <strong>{{ $card['value_formatted'] ?? 'Sem valor' }}</strong>
                                                </div>
                                            </div>
                                        </button>

                                        <footer class="crm-kanban-card-footer">
                                            <div class="crm-kanban-owner">
                                                <span>{{ $card['owner_initials'] }}</span>
                                                <small>{{ $card['owner'] ?: 'Sem responsável' }}</small>
                                            </div>

                                            <small title="{{ $card['last_interaction_at'] ?? 'Sem interação' }}">
                                                {{ $card['last_interaction_label'] }}
                                            </small>
                                        </footer>
                                    </article>
                                @empty
                                    <div class="crm-kanban-empty">
                                        Nenhuma oportunidade nesta etapa com os filtros atuais.
                                    </div>
                                @endforelse
                            </div>

                            @can('create', \App\Models\Oportunidade::class)
                                <button type="button" class="crm-kanban-stage-create" wire:click="openCreateDrawer({{ $column['id'] }})">
                                    + Nova oportunidade aqui
                                </button>
                            @endcan
                        </article>
                    @endforeach
                </section>
            @else
                <section class="crm-kanban-zero">
                    <h3>Nenhuma etapa configurada</h3>
                    <p>Crie as etapas do funil para começar a trabalhar o quadro Kanban.</p>

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
                        <p class="crm-kanban-eyebrow">Fechamento obrigatório</p>
                        <h3 id="crm-close-modal-title">Motivo do fechamento</h3>
                        <p>
                            Para mover esta oportunidade para <strong>{{ $pendingStageName }}</strong>, informe o motivo do encerramento.
                        </p>
                    </div>

                    <label class="crm-field">
                        <span>Motivo</span>
                        <textarea rows="4" wire:model.defer="pendingMoveReason" placeholder="Ex.: proposta aceita, prazo acordado ou perda por preço."></textarea>
                        @error('pendingMoveReason')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <div class="crm-modal-actions">
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="cancelPendingMove">
                            Cancelar
                        </button>

                        <button type="button" class="crm-btn crm-btn-primary" wire:click="confirmPendingStageMove">
                            Confirmar movimentação
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
                        <div>
                            <p class="crm-kanban-eyebrow">{{ $drawerMode === 'create' ? 'Nova oportunidade' : 'Oportunidade selecionada' }}</p>
                            <h3>{{ $selectedOpportunity?->titulo ?? 'Nova oportunidade' }}</h3>
                            <p>
                                {{ $selectedOpportunity?->cliente?->razao_social ?? 'Preencha os dados principais para começar.' }}
                            </p>
                        </div>

                        <div class="crm-drawer-actions">
                            @if ($selectedOpportunity)
                                <a href="{{ \App\Filament\Resources\Oportunidades\OportunidadeResource::getUrl('view', ['record' => $selectedOpportunity]) }}" class="crm-btn crm-btn-secondary">
                                    Visualizar
                                </a>

                                @can('update', $selectedOpportunity)
                                    <a href="{{ \App\Filament\Resources\Oportunidades\OportunidadeResource::getUrl('edit', ['record' => $selectedOpportunity]) }}" class="crm-btn crm-btn-secondary">
                                        Edição completa
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
                            Interações
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'tasks' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'tasks')" @disabled(! $selectedOpportunity)>
                            Tarefas
                        </button>
                        <button type="button" class="{{ $activeDrawerTab === 'movements' ? 'is-active' : '' }}" wire:click="$set('activeDrawerTab', 'movements')" @disabled(! $selectedOpportunity)>
                            Movimentações
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
