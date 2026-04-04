@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para registrar interacoes.</div>
@else
    <div class="crm-drawer-stack">
        <section class="crm-drawer-section">
            <div class="crm-section-heading">
                <h4>Timeline de interacoes</h4>
                <p>Ligue, visite, envie e-mails e mantenha o historico comercial centralizado.</p>
            </div>

            <div class="crm-timeline-list">
                @forelse ($selectedOpportunity->oportunidadeInteracoes as $interaction)
                    <article class="crm-timeline-item is-{{ $interaction->tipo }}" wire:key="interaction-{{ $interaction->id }}">
                        <div class="crm-timeline-body">
                            <p class="crm-timeline-meta">
                                {{ \App\Models\OportunidadeInteracao::tipoOptions()[$interaction->tipo] ?? $interaction->tipo }}
                                · {{ $interaction->user?->name ?? 'Sem usuario' }}
                                · {{ $interaction->ocorreu_em?->format('d/m/Y H:i') }}
                            </p>
                            <p>{{ $interaction->nota }}</p>

                            <div class="crm-entity-actions">
                                @can('update', $interaction)
                                    <button type="button" class="crm-btn crm-btn-secondary" wire:click="editInteraction({{ $interaction->id }})">
                                        Editar
                                    </button>
                                @endcan

                                @can('delete', $interaction)
                                    <button type="button" class="crm-btn crm-btn-danger" wire:click="deleteInteraction({{ $interaction->id }})">
                                        Excluir
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="crm-drawer-empty">Nenhuma interacao registrada ate o momento.</div>
                @endforelse
            </div>
        </section>

        @if (auth()->user()?->can('create', \App\Models\OportunidadeInteracao::class) || filled($interactionForm['id']))
            <form class="crm-drawer-section" wire:submit.prevent="saveInteraction">
                <div class="crm-section-heading">
                    <h4>{{ filled($interactionForm['id']) ? 'Editar interacao' : 'Registrar interacao' }}</h4>
                    <p>As interacoes entram no historico da oportunidade e ajudam a ordenar a ultima atividade.</p>
                </div>

                <div class="crm-drawer-grid">
                    <label class="crm-field">
                        <span>Usuario</span>
                        <select wire:model.defer="interactionForm.user_id">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner['id'] }}">{{ $owner['nome'] }}</option>
                            @endforeach
                        </select>
                        @error('interactionForm.user_id')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Tipo</span>
                        <select wire:model.defer="interactionForm.tipo">
                            @foreach (\App\Models\OportunidadeInteracao::tipoOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('interactionForm.tipo')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Ocorreu em</span>
                        <input type="datetime-local" wire:model.defer="interactionForm.ocorreu_em">
                        @error('interactionForm.ocorreu_em')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field crm-field-full">
                        <span>Nota</span>
                        <textarea rows="5" wire:model.defer="interactionForm.nota"></textarea>
                        @error('interactionForm.nota')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>
                </div>

                <div class="crm-form-actions">
                    <button type="submit" class="crm-btn crm-btn-primary">
                        {{ filled($interactionForm['id']) ? 'Salvar interacao' : 'Registrar interacao' }}
                    </button>

                    @if (filled($interactionForm['id']))
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="resetInteractionForm">
                            Cancelar edicao
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endif
