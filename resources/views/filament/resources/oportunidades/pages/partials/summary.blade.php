<form class="crm-drawer-stack" wire:submit.prevent="saveOpportunity">
    <section class="crm-drawer-section">
        <div class="crm-section-heading">
            <h4>Resumo comercial</h4>
            <p>Atualize os dados principais da negociacao e mantenha a oportunidade pronta para o quadro.</p>
        </div>

        <div class="crm-drawer-grid">
            <label class="crm-field crm-field-full">
                <span>Titulo</span>
                <input type="text" wire:model.defer="opportunityForm.titulo" @disabled(! $canEditOpportunity)>
                @error('opportunityForm.titulo')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="crm-field crm-field-full">
                <span>Cliente</span>
                <select wire:model.live="opportunityForm.cliente_id" @disabled(! $canEditOpportunity)>
                    <option value="">Selecione</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client['id'] }}">{{ $client['nome'] }}</option>
                    @endforeach
                </select>
                @error('opportunityForm.cliente_id')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <div class="crm-info-card">
                <span>Segmento do cliente</span>
                <strong>{{ $selectedClientSegment ?: 'Sem segmento' }}</strong>
            </div>

            <label class="crm-field">
                <span>Etapa</span>
                <select wire:model.live="opportunityForm.etapa_id" @disabled(! $canEditOpportunity)>
                    <option value="">Selecione</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage['id'] }}">{{ $stage['nome'] }}</option>
                    @endforeach
                </select>
                @error('opportunityForm.etapa_id')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="crm-field">
                <span>Responsavel</span>
                <select wire:model.defer="opportunityForm.user_id" @disabled(! $canEditOpportunity)>
                    <option value="">Selecione</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner['id'] }}">{{ $owner['nome'] }}</option>
                    @endforeach
                </select>
                @error('opportunityForm.user_id')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="crm-field">
                <span>Temperatura</span>
                <select wire:model.defer="opportunityForm.temperatura" @disabled(! $canEditOpportunity)>
                    @foreach (\App\Models\Oportunidade::temperaturaOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('opportunityForm.temperatura')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="crm-field">
                <span>Valor estimado</span>
                <input type="number" step="0.01" min="0" wire:model.defer="opportunityForm.valor_estimado" @disabled(! $canEditOpportunity)>
                @error('opportunityForm.valor_estimado')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            @if ($this->selectedStageIsClosing())
                <label class="crm-field crm-field-full">
                    <span>Motivo de fechamento</span>
                    <textarea rows="4" wire:model.defer="opportunityForm.motivo_fechamento" @disabled(! $canEditOpportunity)></textarea>
                    @error('opportunityForm.motivo_fechamento')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>
            @endif

            <label class="crm-field crm-field-full">
                <span>Notas internas</span>
                <textarea rows="5" wire:model.defer="opportunityForm.notas" @disabled(! $canEditOpportunity)></textarea>
                @error('opportunityForm.notas')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>
        </div>
    </section>

    <div class="crm-form-actions">
        @if ($canEditOpportunity)
            <button type="submit" class="crm-btn crm-btn-primary">
                {{ $selectedOpportunity ? 'Salvar alteracoes' : 'Criar oportunidade' }}
            </button>
        @endif

        @if ($selectedOpportunity)
            <button type="button" class="crm-btn crm-btn-secondary" wire:click="openDrawer({{ $selectedOpportunity->id }})">
                Recarregar dados
            </button>
        @endif
    </div>
</form>
