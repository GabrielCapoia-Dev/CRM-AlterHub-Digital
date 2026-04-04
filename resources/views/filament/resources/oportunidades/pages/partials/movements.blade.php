@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para acompanhar o historico de movimentacoes.</div>
@else
    <section class="crm-drawer-section">
        <div class="crm-section-heading">
            <h4>Movimentacoes no funil</h4>
            <p>Auditoria automatica das mudancas de etapa da oportunidade.</p>
        </div>

        <div class="crm-timeline-list">
            @forelse ($selectedOpportunity->oportunidadeMovimentacoes as $movement)
                <article class="crm-timeline-item is-movimento" wire:key="movement-{{ $movement->id }}">
                    <div class="crm-timeline-body">
                        <p class="crm-timeline-meta">
                            {{ $movement->user?->name ?? 'Sem usuario' }}
                            · {{ $movement->movido_em?->format('d/m/Y H:i') }}
                        </p>
                        <p>
                            <strong>{{ $movement->etapaOrigem?->nome ?? 'Sem origem' }}</strong>
                            →
                            <strong>{{ $movement->etapaDestino?->nome ?? 'Sem destino' }}</strong>
                        </p>
                        <small>{{ $movement->motivo ?: 'Sem motivo registrado' }}</small>
                    </div>
                </article>
            @empty
                <div class="crm-drawer-empty">A oportunidade ainda nao possui movimentacoes registradas.</div>
            @endforelse
        </div>
    </section>
@endif
