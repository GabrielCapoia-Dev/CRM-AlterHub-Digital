@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para acompanhar o histórico de movimentações.</div>
@else
    <div class="crm-entity-list">
        @forelse ($selectedOpportunity->oportunidadeMovimentacoes as $movement)
            <article class="crm-entity-card" wire:key="movement-{{ $movement->id }}">
                <div>
                    <h4>{{ $movement->etapaOrigem?->nome ?? 'Sem origem' }} → {{ $movement->etapaDestino?->nome ?? 'Sem destino' }}</h4>
                    <p>{{ $movement->user?->name ?? 'Sem usuário' }} · {{ $movement->movido_em?->format('d/m/Y H:i') }}</p>
                    <small>{{ $movement->motivo ?: 'Sem motivo registrado' }}</small>
                </div>
            </article>
        @empty
            <div class="crm-drawer-empty">A oportunidade ainda não possui movimentações registradas.</div>
        @endforelse
    </div>
@endif
