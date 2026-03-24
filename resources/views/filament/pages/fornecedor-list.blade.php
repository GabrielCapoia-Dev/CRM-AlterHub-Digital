{{-- resources/views/filament/pages/fornecedor-list.blade.php --}}
<x-filament-panels::page>

{{-- ═══════════════════════════════════════════════════════════════ STYLES --}}
@push('styles')
<style>
  /* ── Tokens ─────────────────────────────────────────── */
  :root {
    --clr-bg:          #F4F6FA;
    --clr-surface:     #FFFFFF;
    --clr-border:      #E2E8F0;
    --clr-primary:     #1E40AF;
    --clr-primary-lt:  #EFF6FF;
    --clr-primary-hov: #1D3AA3;
    --clr-text-strong: #0F172A;
    --clr-text-base:   #334155;
    --clr-text-muted:  #64748B;
    --clr-text-faint:  #94A3B8;
    --clr-ok-bg:       #DCFCE7; --clr-ok-tx:   #15803D;
    --clr-wait-bg:     #FEF9C3; --clr-wait-tx:  #854D0E;
    --clr-info-bg:     #DBEAFE; --clr-info-tx:  #1D4ED8;
    --clr-err-bg:      #FEE2E2; --clr-err-tx:   #B91C1C;
    --radius-sm:       6px;
    --radius-md:       10px;
    --radius-lg:       14px;
    --shadow-card:     0 1px 3px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.05);
    --font-mono:       'JetBrains Mono', 'Fira Mono', ui-monospace, monospace;
    --transition:      .18s ease;
  }

  /* ── Layout ──────────────────────────────────────────── */
  .fl-page-wrap {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 24px 0;
  }

  /* ── Page header ─────────────────────────────────────── */
  .fl-page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .fl-page-head h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--clr-text-strong);
    letter-spacing: -.3px;
    margin: 0 0 4px;
  }
  .fl-page-head p {
    font-size: 13.5px;
    color: var(--clr-text-muted);
    margin: 0;
    max-width: 560px;
    line-height: 1.5;
  }

  /* ── Botão primário ──────────────────────────────────── */
  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    background: var(--clr-primary);
    color: #fff;
    font-size: 13.5px;
    font-weight: 600;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    white-space: nowrap;
    transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
    box-shadow: 0 1px 2px rgba(30,64,175,.2), 0 4px 12px rgba(30,64,175,.15);
    text-decoration: none;
  }
  .btn-primary:hover  { background: var(--clr-primary-hov); transform: translateY(-1px); box-shadow: 0 2px 4px rgba(30,64,175,.25), 0 6px 18px rgba(30,64,175,.18); }
  .btn-primary:active { transform: translateY(0); }

  /* ── Toolbar ─────────────────────────────────────────── */
  .fl-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-lg);
    padding: 12px 16px;
    box-shadow: var(--shadow-card);
  }

  .search-wrap {
    flex: 1;
    min-width: 200px;
    position: relative;
  }
  .search-wrap svg {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
  }
  .search-wrap input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    font-size: 13.5px;
    color: var(--clr-text-base);
    background: var(--clr-bg);
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition);
    box-sizing: border-box;
  }
  .search-wrap input::placeholder { color: var(--clr-text-faint); }
  .search-wrap input:focus {
    border-color: var(--clr-primary);
    box-shadow: 0 0 0 3px rgba(30,64,175,.1);
    background: #fff;
  }

  .fl-toolbar select {
    padding: 8px 32px 8px 12px;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    font-size: 13.5px;
    color: var(--clr-text-base);
    background: var(--clr-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none;
    outline: none;
    cursor: pointer;
    transition: border-color var(--transition), box-shadow var(--transition);
  }
  .fl-toolbar select:focus {
    border-color: var(--clr-primary);
    box-shadow: 0 0 0 3px rgba(30,64,175,.1);
    background-color: #fff;
  }

  .btn-clear-filters {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 13px;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-md);
    background: transparent;
    font-size: 13px;
    color: var(--clr-text-muted);
    cursor: pointer;
    white-space: nowrap;
    transition: all var(--transition);
  }
  .btn-clear-filters:hover { border-color: var(--clr-primary); color: var(--clr-primary); background: var(--clr-primary-lt); }

  /* ── Card da tabela ──────────────────────────────────── */
  .table-card {
    background: var(--clr-surface);
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    overflow: hidden;
  }

  .table-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 14px;
    border-bottom: 1px solid var(--clr-border);
    gap: 12px;
  }
  .table-card-head h2 {
    font-size: 15px;
    font-weight: 700;
    color: var(--clr-text-strong);
    margin: 0;
  }
  .table-card-head .record-count {
    font-size: 12.5px;
    color: var(--clr-text-muted);
    background: var(--clr-bg);
    border: 1px solid var(--clr-border);
    border-radius: 20px;
    padding: 2px 10px;
  }

  /* ── Tabela ──────────────────────────────────────────── */
  .table-scroll { overflow-x: auto; }

  table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
  }
  table.data-table thead tr {
    background: var(--clr-bg);
    border-bottom: 2px solid var(--clr-border);
  }
  table.data-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--clr-text-muted);
    white-space: nowrap;
  }
  table.data-table th.sortable {
    cursor: pointer;
    user-select: none;
  }
  table.data-table th.sortable:hover { color: var(--clr-primary); }
  table.data-table th .sort-icon { margin-left: 4px; opacity: .45; font-size: 10px; }
  table.data-table th.sort-asc  .sort-icon,
  table.data-table th.sort-desc .sort-icon { opacity: 1; color: var(--clr-primary); }

  table.data-table tbody tr {
    border-bottom: 1px solid var(--clr-border);
    transition: background var(--transition);
  }
  table.data-table tbody tr:last-child { border-bottom: none; }
  table.data-table tbody tr:hover { background: #F8FAFD; }

  table.data-table td {
    padding: 13px 16px;
    color: var(--clr-text-base);
    vertical-align: middle;
  }
  table.data-table td strong {
    color: var(--clr-text-strong);
    font-weight: 600;
  }
  .mono { font-family: var(--font-mono); font-size: 12.5px; color: var(--clr-text-muted); }

  /* ── Tags de status ──────────────────────────────────── */
  .tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
  }
  .tag::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; }
  .tag-ok   { background: var(--clr-ok-bg);   color: var(--clr-ok-tx);   } .tag-ok::before   { background: var(--clr-ok-tx); }
  .tag-wait { background: var(--clr-wait-bg);  color: var(--clr-wait-tx); } .tag-wait::before { background: var(--clr-wait-tx); }
  .tag-info { background: var(--clr-info-bg);  color: var(--clr-info-tx); } .tag-info::before { background: var(--clr-info-tx); }
  .tag-err  { background: var(--clr-err-bg);   color: var(--clr-err-tx);  } .tag-err::before  { background: var(--clr-err-tx); }

  /* ── Ações ───────────────────────────────────────────── */
  .actions { display: flex; gap: 6px; align-items: center; }
  .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--clr-text-muted);
    cursor: pointer;
    font-size: 15px;
    transition: all var(--transition);
    line-height: 1;
  }
  .btn-icon:hover { border-color: var(--clr-primary); color: var(--clr-primary); background: var(--clr-primary-lt); }
  .btn-icon.btn-del:hover { border-color: #DC2626; color: #DC2626; background: #FEF2F2; }

  /* ── Rodapé da tabela / paginação ────────────────────── */
  .table-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-top: 1px solid var(--clr-border);
    font-size: 13px;
    color: var(--clr-text-muted);
    flex-wrap: wrap;
  }
  .pager { display: flex; gap: 6px; align-items: center; }
  .pager button {
    padding: 6px 14px;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-sm);
    background: transparent;
    font-size: 13px;
    cursor: pointer;
    color: var(--clr-text-base);
    transition: all var(--transition);
  }
  .pager button:hover:not(:disabled) { border-color: var(--clr-primary); color: var(--clr-primary); background: var(--clr-primary-lt); }
  .pager button:disabled { opacity: .45; cursor: not-allowed; }
  .pager .page-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    border: 1px solid var(--clr-primary);
    border-radius: var(--radius-sm);
    background: var(--clr-primary);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
  }

  /* ── Estado vazio ────────────────────────────────────── */
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 56px 24px;
    color: var(--clr-text-muted);
    text-align: center;
  }
  .empty-state svg { opacity: .35; }
  .empty-state p { margin: 0; font-size: 14px; }
  .empty-state small { font-size: 12.5px; color: var(--clr-text-faint); }

  /* ── Responsividade ──────────────────────────────────── */
  @media (max-width: 640px) {
    .fl-page-head  { flex-direction: column; }
    .fl-toolbar    { gap: 8px; }
    .fl-toolbar select { flex: 1 1 140px; }
  }
</style>
@endpush

{{-- ═══════════════════════════════════════════════════════════════ MARKUP --}}
<div class="fl-page-wrap" x-data>

  {{-- ── Cabeçalho ──────────────────────────────────────────────── --}}
  <div class="fl-page-head">
    <div>
      <h1>Gestão de fornecedores</h1>
      <p>Cadastro de parceiros de suprimento: reagentes, equipamentos, serviços e logística.
         Cadastro e edição abrem em modal nesta mesma tela.</p>
    </div>
    {{-- $dispatch envia o evento para o window, onde o FornecedorForm escuta via @open-fornecedor-modal.window --}}
    <button type="button" class="btn-primary"
            @click="$dispatch('open-fornecedor-modal', { uuid: null })">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M12 5v14M5 12h14"/>
      </svg>
      Novo fornecedor
    </button>
  </div>

  {{-- ── Toolbar de busca e filtros ─────────────────────────────── --}}
  <div class="fl-toolbar">
    <div class="search-wrap">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
      </svg>
      <input type="search"
             placeholder="Razão social, CNPJ ou código…"
             wire:model.live.debounce.350ms="search"
             autocomplete="off">
    </div>

    <select wire:model.live="filtroCategoria" aria-label="Filtrar por categoria">
      <option value="">Todas as categorias</option>
      @foreach($categorias as $cat)
        <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
      @endforeach
    </select>

    <select wire:model.live="filtroStatus" aria-label="Filtrar por status">
      <option value="">Todos os status</option>
      @foreach($statusList as $st)
        <option value="{{ $st->id }}">{{ $st->nome }}</option>
      @endforeach
    </select>

    @if($search || $filtroCategoria || $filtroStatus)
      <button type="button" class="btn-clear-filters" wire:click="limparFiltros">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
        Limpar
      </button>
    @endif
  </div>

  {{-- ── Card / Tabela ───────────────────────────────────────────── --}}
  <div class="table-card">
    <div class="table-card-head">
      <h2>Fornecedores cadastrados</h2>
      <span class="record-count">{{ $fornecedores->total() }} {{ Str::plural('registro', $fornecedores->total()) }}</span>
    </div>

    <div class="table-scroll">
      <table class="data-table" aria-label="Lista de fornecedores">
        <thead>
          <tr>
            <th class="sortable" wire:click="sortBy('codigo_interno')">
              Código
              <span class="sort-icon">{{ $sortCol === 'codigo_interno' ? ($sortDir === 'asc' ? '▲' : '▼') : '⇅' }}</span>
            </th>
            <th class="sortable" wire:click="sortBy('razao_social')">
              Razão social
              <span class="sort-icon">{{ $sortCol === 'razao_social' ? ($sortDir === 'asc' ? '▲' : '▼') : '⇅' }}</span>
            </th>
            <th>CNPJ</th>
            <th>Categoria</th>
            <th>Prazo pag.</th>
            <th>Status</th>
            <th style="width:110px">Ações</th>
          </tr>
        </thead>

        <tbody>
          @forelse($fornecedores as $f)
            <tr>
              <td class="mono">{{ $f->codigo_interno ?? '—' }}</td>
              <td><strong>{{ $f->razao_social }}</strong>
                @if($f->nome_fantasia)
                  <br><span style="font-size:12px;color:var(--clr-text-muted)">{{ $f->nome_fantasia }}</span>
                @endif
              </td>
              <td class="mono">{{ $f->cnpj }}</td>
              <td>{{ $f->categoriaFornecimento?->nome ?? '—' }}</td>
              <td>{{ $f->prazoPagamento?->nome ?? '—' }}</td>
              <td>
                @php $tag = statusTag($f->statusHomologacao?->nome) @endphp
                <span class="tag {{ $tag['class'] }}">{{ $tag['label'] }}</span>
              </td>
              <td class="actions">
                <button type="button" class="btn-icon"
                        @click="$dispatch('open-fornecedor-modal', { uuid: '{{ $f->uuid }}' })"
                        title="Editar {{ $f->razao_social }}"
                        aria-label="Editar {{ $f->razao_social }}">✎</button>
                <button type="button" class="btn-icon btn-del"
                        wire:click="confirmDelete('{{ $f->uuid }}')"
                        title="Excluir"
                        aria-label="Excluir {{ $f->razao_social }}">✕</button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="7.5 4.21 12 6.81 16.5 4.21"/>
                    <polyline points="7.5 19.79 7.5 14.6 3 12"/>
                    <polyline points="21 12 16.5 14.6 16.5 19.79"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                  </svg>
                  <p>Nenhum fornecedor encontrado</p>
                  <small>Tente ajustar a busca ou os filtros aplicados.</small>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Rodapé / paginação --}}
    <div class="table-foot">
      <span>
        Mostrando
        {{ $fornecedores->firstItem() ?? 0 }}–{{ $fornecedores->lastItem() ?? 0 }}
        de {{ $fornecedores->total() }}
      </span>
      <div class="pager">
        <button type="button"
                wire:click="previousPage"
                @disabled(!$fornecedores->onFirstPage())
                :disabled="!$fornecedores->onFirstPage()"
                aria-label="Página anterior">Anterior</button>
        <span class="page-num">{{ $fornecedores->currentPage() }}</span>
        <button type="button"
                wire:click="nextPage"
                @disabled(!$fornecedores->hasMorePages())
                :disabled="!$fornecedores->hasMorePages()"
                aria-label="Próxima página">Próxima</button>
      </div>
    </div>
  </div>

</div>{{-- /x-data --}}

{{-- ── Modal de formulário (Livewire embutido) ─────────────────────── --}}
@livewire('filament.pages.fornecedor.fornecedor-form')

</x-filament-panels::page>