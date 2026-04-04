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
        $pendingStageName = collect($stages)->firstWhere('id', $pendingMoveStageId)['nome'] ?? 'Encerramento';
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
