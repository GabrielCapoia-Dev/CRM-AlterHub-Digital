<x-filament-panels::page>
    {{--
  resources/views/filament/pages/fornecedor-form.blade.php
  Componente Livewire embutido na página de listagem via @livewire('fornecedor-form')
  ou disparado pelo evento open-fornecedor-modal.
--}}
    @push('styles')
    <style>
        /* ── Tokens (herda da listagem se já carregado) ──────── */
        :root {
            --clr-bg: #F4F6FA;
            --clr-surface: #FFFFFF;
            --clr-border: #E2E8F0;
            --clr-primary: #1E40AF;
            --clr-primary-lt: #EFF6FF;
            --clr-primary-hov: #1D3AA3;
            --clr-danger: #DC2626;
            --clr-danger-lt: #FEF2F2;
            --clr-danger-hov: #B91C1C;
            --clr-text-strong: #0F172A;
            --clr-text-base: #334155;
            --clr-text-muted: #64748B;
            --clr-text-faint: #94A3B8;
            --clr-req: #DC2626;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-modal: 0 8px 32px rgba(0, 0, 0, .14), 0 2px 8px rgba(0, 0, 0, .08);
            --transition: .18s ease;
        }

        /* ── Overlay ──────────────────────────────────────────── */
        .forn-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .45);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 9000;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px 24px;
            overflow-y: auto;
            opacity: 0;
            pointer-events: none;
            transition: opacity .22s ease;
        }

        .forn-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        /* ── Painel ───────────────────────────────────────────── */
        .forn-panel {
            width: 100%;
            max-width: 780px;
            background: var(--clr-surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-modal);
            border: 1px solid var(--clr-border);
            display: flex;
            flex-direction: column;
            transform: translateY(16px) scale(.985);
            transition: transform .24s cubic-bezier(.22, .68, 0, 1.2), opacity .22s ease;
            opacity: 0;
        }

        .forn-overlay.open .forn-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        /* ── Cabeçalho do modal ──────────────────────────────── */
        .forn-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 28px 20px;
            border-bottom: 1px solid var(--clr-border);
            position: sticky;
            top: 0;
            background: var(--clr-surface);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            z-index: 10;
        }

        .forn-head h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--clr-text-strong);
            margin: 0 0 4px;
            letter-spacing: -.2px;
        }

        .forn-head p {
            font-size: 13px;
            color: var(--clr-text-muted);
            margin: 0;
            line-height: 1.5;
            max-width: 480px;
        }

        .forn-head .req {
            color: var(--clr-req);
        }

        .id-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 3px 10px;
            background: var(--clr-bg);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            font-size: 11.5px;
            color: var(--clr-text-muted);
            font-family: 'JetBrains Mono', 'Fira Mono', ui-monospace, monospace;
        }

        .forn-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            background: transparent;
            font-size: 20px;
            color: var(--clr-text-muted);
            cursor: pointer;
            line-height: 1;
            flex-shrink: 0;
            transition: all var(--transition);
        }

        .forn-close:hover {
            border-color: var(--clr-danger);
            color: var(--clr-danger);
            background: var(--clr-danger-lt);
        }

        /* ── Corpo (scrollável) ───────────────────────────────── */
        .forn-body {
            padding: 0 28px 24px;
            overflow-y: auto;
            max-height: calc(100vh - 260px);
        }

        /* ── Seções do formulário ─────────────────────────────── */
        .form-section {
            padding: 20px 0 0;
        }

        .form-section+.form-section {
            border-top: 1px solid var(--clr-border);
            margin-top: 4px;
        }

        .form-section>h3 {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--clr-text-muted);
            margin: 0 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section>h3::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--clr-border);
        }

        /* ── Grid de campos ───────────────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 16px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Campo individual ─────────────────────────────────── */
        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field.col-3 {
            grid-column: span 1;
        }

        .field label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--clr-text-base);
        }

        .field label .req {
            color: var(--clr-req);
            margin-left: 1px;
        }

        .field input,
        .field select,
        .field textarea {
            padding: 8px 11px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: 13.5px;
            color: var(--clr-text-base);
            background: var(--clr-bg);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
        }

        .field input::placeholder,
        .field textarea::placeholder {
            color: var(--clr-text-faint);
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, .1);
            background: #fff;
        }

        .field input:disabled {
            color: var(--clr-text-faint);
            cursor: not-allowed;
            background: var(--clr-bg);
        }

        .field select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
            cursor: pointer;
        }

        .field textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.6;
        }

        .field .field-hint {
            font-size: 11.5px;
            color: var(--clr-text-faint);
            line-height: 1.4;
        }

        /* Erro de validação */
        .field .error-msg {
            font-size: 11.5px;
            color: var(--clr-danger);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field input.invalid,
        .field select.invalid,
        .field textarea.invalid {
            border-color: var(--clr-danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, .1);
        }

        /* ── Rodapé / Ações ───────────────────────────────────── */
        .forn-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 16px 28px 20px;
            border-top: 1px solid var(--clr-border);
            background: var(--clr-surface);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            flex-wrap: wrap;
        }

        .forn-actions .right {
            display: flex;
            gap: 8px;
        }

        /* ── Botões ───────────────────────────────────────────── */
        .forn-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
            line-height: 1;
        }

        .forn-btn-primary {
            background: var(--clr-primary);
            color: #fff;
            border-color: var(--clr-primary);
            box-shadow: 0 1px 2px rgba(30, 64, 175, .2), 0 3px 10px rgba(30, 64, 175, .12);
        }

        .forn-btn-primary:hover {
            background: var(--clr-primary-hov);
            border-color: var(--clr-primary-hov);
            transform: translateY(-1px);
        }

        .forn-btn-primary:active {
            transform: translateY(0);
        }

        .forn-btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .forn-btn-ghost {
            background: transparent;
            color: var(--clr-text-base);
            border-color: var(--clr-border);
        }

        .forn-btn-ghost:hover {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
            background: var(--clr-primary-lt);
        }

        .forn-btn-danger {
            background: transparent;
            color: var(--clr-danger);
            border-color: #FECACA;
        }

        .forn-btn-danger:hover {
            background: var(--clr-danger-lt);
            border-color: var(--clr-danger);
        }

        /* Loading spinner no botão salvar */
        .spin {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
    @endpush

    {{-- ═══════════════════════════════════════════════════════════════ MODAL --}}
    <div
        class="forn-overlay {{ $open ? 'open' : '' }}"
        id="modal-fornecedor"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-forn-title"
        wire:keydown.escape="fechar"
        x-data
        @open-fornecedor-modal.window="$wire.abrir($event.detail.uuid)"
        @keydown.escape.window="$wire.fechar()">
        {{-- Clique fora fecha --}}
        <div style="position:fixed;inset:0;z-index:-1" wire:click="fechar"></div>

        <div class="forn-panel" @click.stop role="document">

            {{-- ── Cabeçalho ──────────────────────────────────────── --}}
            <div class="forn-head">
                <div>
                    <h2 id="modal-forn-title">
                        {{ $uuid ? 'Editar fornecedor' : 'Novo fornecedor' }}
                    </h2>
                    <p>
                        Inclua dados fiscais, categoria de suprimento e condições comerciais.
                        Campos com <span class="req">*</span> são obrigatórios.
                    </p>
                    @if($uuid)
                    <div class="id-chip">
                        UUID: {{ $uuid }}
                    </div>
                    @endif
                </div>
                <button type="button" class="forn-close" wire:click="fechar" aria-label="Fechar modal">×</button>
            </div>

            {{-- ── Corpo ──────────────────────────────────────────── --}}
            <div class="forn-body">
                <form id="form-fornecedor" wire:submit.prevent="salvar" novalidate>

                    {{-- ─ Dados da empresa ─────────────────────────── --}}
                    <div class="form-section">
                        <h3>Dados da empresa</h3>
                        <div class="form-grid">

                            <div class="field full">
                                <label for="f_codigo">Código interno</label>
                                <input id="f_codigo" type="text"
                                    value="{{ $form['codigo_interno'] ?? '' }}"
                                    placeholder="Gerado automaticamente ao salvar"
                                    disabled>
                                <span class="field-hint">Prefixo sugerido: FOR-AAAA-XXX</span>
                            </div>

                            <div class="field full">
                                <label for="f_razao">Razão social <span class="req">*</span></label>
                                <input id="f_razao" type="text"
                                    wire:model="form.razao_social"
                                    placeholder="Ex.: ReagentBio Distribuidora Ltda."
                                    autocomplete="organization"
                                    class="{{ $errors->has('form.razao_social') ? 'invalid' : '' }}">
                                @error('form.razao_social')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_fantasia">Nome fantasia</label>
                                <input id="f_fantasia" type="text"
                                    wire:model="form.nome_fantasia"
                                    placeholder="Nome comercial">
                            </div>

                            <div class="field">
                                <label for="f_cnpj">CNPJ <span class="req">*</span></label>
                                <input id="f_cnpj" type="text"
                                    wire:model="form.cnpj"
                                    placeholder="00.000.000/0001-00"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    class="{{ $errors->has('form.cnpj') ? 'invalid' : '' }}">
                                @error('form.cnpj')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_ie">Inscrição estadual</label>
                                <input id="f_ie" type="text"
                                    wire:model="form.inscricao_estadual"
                                    placeholder="Isento ou número">
                            </div>

                            <div class="field">
                                <label for="f_categoria">Categoria de fornecimento <span class="req">*</span></label>
                                <select id="f_categoria"
                                    wire:model="form.id_categoria_fornecimento"
                                    class="{{ $errors->has('form.id_categoria_fornecimento') ? 'invalid' : '' }}">
                                    <option value="">Selecione…</option>
                                    @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                                    @endforeach
                                </select>
                                @error('form.id_categoria_fornecimento')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_status">Status de homologação</label>
                                <select id="f_status" wire:model="form.id_status_homologacao">
                                    <option value="">Selecione…</option>
                                    @foreach($statusList as $st)
                                    <option value="{{ $st->id }}">{{ $st->nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                    </div>

                    {{-- ─ Condições comerciais ──────────────────────── --}}
                    <div class="form-section">
                        <h3>Condições comerciais</h3>
                        <div class="form-grid">

                            <div class="field">
                                <label for="f_prazo">Prazo de pagamento <span class="req">*</span></label>
                                <select id="f_prazo"
                                    wire:model="form.id_prazo_pagamento"
                                    class="{{ $errors->has('form.id_prazo_pagamento') ? 'invalid' : '' }}">
                                    <option value="">Selecione…</option>
                                    @foreach($prazos as $p)
                                    <option value="{{ $p->id }}">{{ $p->nome }}</option>
                                    @endforeach
                                </select>
                                @error('form.id_prazo_pagamento')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_forma">Forma de pagamento <span class="req">*</span></label>
                                <select id="f_forma"
                                    wire:model="form.id_forma_pagamento"
                                    class="{{ $errors->has('form.id_forma_pagamento') ? 'invalid' : '' }}">
                                    <option value="">Selecione…</option>
                                    @foreach($formas as $f)
                                    <option value="{{ $f->id }}">{{ $f->nome }}</option>
                                    @endforeach
                                </select>
                                @error('form.id_forma_pagamento')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                    </div>

                    {{-- ─ Contato principal ─────────────────────────── --}}
                    <div class="form-section">
                        <h3>Contato principal</h3>
                        <div class="form-grid">

                            <div class="field">
                                <label for="f_nome">Nome completo <span class="req">*</span></label>
                                <input id="f_nome" type="text"
                                    wire:model="form.nome_completo"
                                    placeholder="Comercial ou fiscal"
                                    autocomplete="name"
                                    class="{{ $errors->has('form.nome_completo') ? 'invalid' : '' }}">
                                @error('form.nome_completo')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_cargo">Cargo</label>
                                <input id="f_cargo" type="text"
                                    wire:model="form.cargo"
                                    placeholder="Ex.: Representante comercial">
                            </div>

                            <div class="field">
                                <label for="f_email">E-mail <span class="req">*</span></label>
                                <input id="f_email" type="email"
                                    wire:model="form.email"
                                    placeholder="contato@fornecedor.com.br"
                                    autocomplete="email"
                                    class="{{ $errors->has('form.email') ? 'invalid' : '' }}">
                                @error('form.email')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field">
                                <label for="f_tel">Telefone / WhatsApp <span class="req">*</span></label>
                                <input id="f_tel" type="tel"
                                    wire:model="form.telefone"
                                    placeholder="(11) 99999-0000"
                                    autocomplete="tel"
                                    class="{{ $errors->has('form.telefone') ? 'invalid' : '' }}">
                                @error('form.telefone')
                                <span class="error-msg">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                    </div>

                    {{-- ─ Endereço ───────────────────────────────────── --}}
                    <div class="form-section">
                        <h3>Endereço</h3>
                        <div class="form-grid">

                            <div class="field">
                                <label for="f_cep">CEP</label>
                                <input id="f_cep" type="text"
                                    wire:model.lazy="form.cep"
                                    wire:change="buscarCep($event.target.value)"
                                    placeholder="00000-000"
                                    inputmode="numeric"
                                    autocomplete="postal-code">
                            </div>

                            <div class="field">
                                <label for="f_uf">UF</label>
                                <select id="f_uf" wire:model="form.uf" autocomplete="address-level1">
                                    <option value="">—</option>
                                    @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                    <option value="{{ $uf }}">{{ $uf }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field full">
                                <label for="f_logra">Logradouro</label>
                                <input id="f_logra" type="text"
                                    wire:model="form.logradouro"
                                    placeholder="Rua, avenida…"
                                    autocomplete="street-address">
                            </div>

                            <div class="field">
                                <label for="f_num">Número</label>
                                <input id="f_num" type="text" wire:model="form.numero" autocomplete="off">
                            </div>

                            <div class="field">
                                <label for="f_comp">Complemento</label>
                                <input id="f_comp" type="text" wire:model="form.complemento" autocomplete="off">
                            </div>

                            <div class="field">
                                <label for="f_bairro">Bairro</label>
                                <input id="f_bairro" type="text" wire:model="form.bairro" autocomplete="off">
                            </div>

                            <div class="field">
                                <label for="f_cidade">Cidade</label>
                                <input id="f_cidade" type="text"
                                    wire:model="form.cidade"
                                    autocomplete="address-level2">
                            </div>

                        </div>
                    </div>

                    {{-- ─ Observações ───────────────────────────────── --}}
                    <div class="form-section">
                        <h3>Homologação e observações</h3>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="f_obs">Certificações, SLA e notas internas</label>
                                <textarea id="f_obs"
                                    wire:model="form.observacoes"
                                    placeholder="Ex.: ISO 9001, lead time médio, contato de emergência…"></textarea>
                            </div>
                        </div>
                    </div>

                </form>
            </div>{{-- /forn-body --}}

            {{-- ── Ações ────────────────────────────────────────── --}}
            <div class="forn-actions">
                <div>
                    @if($uuid)
                    <button type="button" class="forn-btn forn-btn-danger"
                        wire:click="confirmarExclusao"
                        wire:loading.attr="disabled">
                        Excluir cadastro
                    </button>
                    @endif
                </div>
                <div class="right">
                    <button type="button" class="forn-btn forn-btn-ghost"
                        wire:click="fechar"
                        wire:loading.attr="disabled">
                        Cancelar
                    </button>
                    <button type="submit" form="form-fornecedor" class="forn-btn forn-btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="salvar">
                        <span wire:loading.remove wire:target="salvar">
                            {{ $uuid ? 'Atualizar' : 'Salvar fornecedor' }}
                        </span>
                        <span wire:loading wire:target="salvar" style="display:flex;align-items:center;gap:6px">
                            <span class="spin"></span> Salvando…
                        </span>
                    </button>
                </div>
            </div>

        </div>{{-- /forn-panel --}}
    </div>{{-- /forn-overlay --}}
</x-filament-panels::page>