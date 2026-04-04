@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para registrar interações.</div>
@else
    <div class="crm-drawer-stack">
        <div class="crm-entity-list">
            @forelse ($selectedOpportunity->oportunidadeInteracoes as $interaction)
                <article class="crm-entity-card" wire:key="interaction-{{ $interaction->id }}">
                    <div>
                        <h4>{{ \App\Models\OportunidadeInteracao::tipoOptions()[$interaction->tipo] ?? $interaction->tipo }}</h4>
                        <p>{{ $interaction->user?->name ?? 'Sem usuário' }} · {{ $interaction->ocorreu_em?->format('d/m/Y H:i') }}</p>
                        <small>{{ $interaction->nota }}</small>
                    </div>

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
                </article>
            @empty
                <div class="crm-drawer-empty">Nenhuma interação registrada até o momento.</div>
            @endforelse
        </div>

        @if (auth()->user()?->can('create', \App\Models\OportunidadeInteracao::class) || filled($interactionForm['id']))
            <form class="crm-drawer-form" wire:submit.prevent="saveInteraction">
                <div class="crm-drawer-grid">
                    <label class="crm-field">
                        <span>Usuário</span>
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
                        {{ filled($interactionForm['id']) ? 'Salvar interação' : 'Registrar interação' }}
                    </button>

                    @if (filled($interactionForm['id']))
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="resetInteractionForm">
                            Cancelar edição
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endif
