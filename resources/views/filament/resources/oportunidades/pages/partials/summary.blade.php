@php
    $selectedClient = $this->getSelectedClient();
    $clientMode = $opportunityForm['client_mode'] ?? \App\Services\CRM\OportunidadeClienteService::MODE_EXISTING;
    $clientLookupStatus = $opportunityForm['client_lookup_status'] ?? '';
    $clientLookupMessage = $opportunityForm['client_lookup_message'] ?? '';
    $clientStatuses = $this->getClientStatusOptions();
    $ufOptions = $this->getUfOptions();
@endphp

<form class="crm-drawer-stack" wire:submit.prevent="saveOpportunity">
    <section class="crm-drawer-section">
        <div class="crm-section-heading">
            <h4>Resumo comercial</h4>
        </div>

        <div class="crm-drawer-grid">
            <label class="crm-field crm-field-full">
                <span>Titulo</span>
                <input type="text" wire:model.defer="opportunityForm.titulo" @disabled(! $canEditOpportunity)>
                @error('opportunityForm.titulo')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </label>

            <section class="crm-client-panel crm-field-full">
                <div class="crm-client-toolbar">
                    <label class="crm-field crm-client-search-field">
                        <span>Buscar cliente</span>
                        <input
                            type="text"
                            wire:model.live.debounce.350ms="opportunityForm.client_lookup"
                            placeholder="Codigo ou CNPJ"
                            @disabled(! $canEditOpportunity)
                        >
                        @error('opportunityForm.client_lookup')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    @if ($canEditOpportunity)
                        <div class="crm-client-actions">
                            <button type="button" class="crm-btn crm-btn-primary" wire:click="searchClient">
                                Buscar cliente
                            </button>

                            <button
                                type="button"
                                class="crm-btn crm-btn-secondary {{ $clientMode === \App\Services\CRM\OportunidadeClienteService::MODE_NEW ? 'is-active' : '' }}"
                                wire:click="activateNewClientForm"
                            >
                                Novo Cliente
                            </button>
                        </div>
                    @endif
                </div>

                @if ($selectedClient)
                    <div class="crm-client-feedback is-success">
                        <div class="crm-client-feedback-copy">
                            <span>Cliente vinculado</span>
                            <strong>{{ $selectedClient->razao_social }}</strong>
                            <p>
                                {{ collect([
                                    $selectedClient->codigo_interno ? 'Codigo ' . $selectedClient->codigo_interno : null,
                                    $selectedClient->cnpj ? 'CNPJ ' . $selectedClient->cnpj : null,
                                    $selectedClient->nome_completo ?: null,
                                    $selectedClient->email ?: null,
                                ])->filter()->implode(' | ') ?: 'Cadastro carregado para esta oportunidade.' }}
                            </p>
                        </div>

                        <div class="crm-client-feedback-meta">
                            @if ($selectedClient->categoriaSegmento?->nome)
                                <span>{{ $selectedClient->categoriaSegmento->nome }}</span>
                            @endif

                            @if ($selectedClient->statusCliente?->nome)
                                <span>{{ $selectedClient->statusCliente->nome }}</span>
                            @endif
                        </div>
                    </div>
                @elseif ($clientLookupMessage || $clientMode === \App\Services\CRM\OportunidadeClienteService::MODE_NEW)
                    <div class="crm-client-feedback {{ $clientLookupStatus === 'missing' ? 'is-warning' : 'is-info' }}">
                        <div class="crm-client-feedback-copy">
                            <span>{{ $clientLookupStatus === 'missing' ? 'Busca' : 'Cadastro' }}</span>
                            <strong>{{ $clientLookupStatus === 'missing' ? 'Cliente nao encontrado.' : 'Novo cliente em preenchimento.' }}</strong>
                            <p>{{ $clientLookupStatus === 'missing' ? 'Use o botao Novo Cliente para seguir com o cadastro.' : 'Preencha os dados do cliente abaixo.' }}</p>
                        </div>
                    </div>
                @endif

                @error('opportunityForm.client_cnpj')
                    <small class="crm-field-error">{{ $message }}</small>
                @enderror
            </section>

            @if ($clientMode === \App\Services\CRM\OportunidadeClienteService::MODE_NEW)
                <label class="crm-field">
                    <span>Razao social</span>
                    <input type="text" wire:model.defer="opportunityForm.client_razao_social" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_razao_social')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Nome fantasia</span>
                    <input type="text" wire:model.defer="opportunityForm.client_nome_fantasia" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_nome_fantasia')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>CNPJ</span>
                    <input type="text" wire:model.defer="opportunityForm.client_cnpj" placeholder="00.000.000/0001-00" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_cnpj')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Segmento</span>
                    <select wire:model.defer="opportunityForm.client_segmento_id" @disabled(! $canEditOpportunity)>
                        <option value="">Selecione</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment['id'] }}">{{ $segment['nome'] }}</option>
                        @endforeach
                    </select>
                    @error('opportunityForm.client_segmento_id')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Status do cliente</span>
                    <select wire:model.defer="opportunityForm.client_status_id" @disabled(! $canEditOpportunity)>
                        <option value="">Selecione</option>
                        @foreach ($clientStatuses as $status)
                            <option value="{{ $status['id'] }}">{{ $status['nome'] }}</option>
                        @endforeach
                    </select>
                    @error('opportunityForm.client_status_id')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Contato principal</span>
                    <input type="text" wire:model.defer="opportunityForm.client_nome_completo" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_nome_completo')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Cargo</span>
                    <input type="text" wire:model.defer="opportunityForm.client_cargo" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_cargo')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>E-mail</span>
                    <input type="email" wire:model.defer="opportunityForm.client_email" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_email')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Telefone / WhatsApp</span>
                    <input type="text" wire:model.defer="opportunityForm.client_telefone" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_telefone')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>Cidade</span>
                    <input type="text" wire:model.defer="opportunityForm.client_cidade" @disabled(! $canEditOpportunity)>
                    @error('opportunityForm.client_cidade')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field">
                    <span>UF</span>
                    <select wire:model.defer="opportunityForm.client_uf" @disabled(! $canEditOpportunity)>
                        <option value="">Selecione</option>
                        @foreach ($ufOptions as $uf => $label)
                            <option value="{{ $uf }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('opportunityForm.client_uf')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="crm-field crm-field-full">
                    <span>Observacoes do cliente</span>
                    <textarea rows="4" wire:model.defer="opportunityForm.client_observacao" @disabled(! $canEditOpportunity)></textarea>
                    @error('opportunityForm.client_observacao')
                        <small class="crm-field-error">{{ $message }}</small>
                    @enderror
                </label>
            @endif

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
