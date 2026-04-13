@php
    $ultimaAtualizacao = $record?->updated_at?->format('d/m/Y H:i') ?? '-';
    $criadoEm = $record?->created_at?->format('d/m/Y H:i');
@endphp

<div class="insumo-history">
    <div class="insumo-history__card">
        <span class="insumo-history__label">Ultima atualizacao do insumo</span>
        <div class="insumo-history__value">{{ $ultimaAtualizacao }}</div>
        <p class="insumo-history__hint">Exibida a partir do ultimo salvamento deste cadastro.</p>
    </div>

    <div class="insumo-history__card">
        <span class="insumo-history__label">Movimentacoes recentes (entrada)</span>

        <div class="insumo-history__log">
            @if (! $record)
                <span class="insumo-history__empty">Salve o cadastro para ver historico de movimentacoes.</span>
            @elseif ($criadoEm)
                <span class="insumo-history__empty">
                    Cadastro salvo em {{ $criadoEm }}. A integracao de movimentacoes operacionais ainda nao esta disponivel nesta tela.
                </span>
            @else
                <span class="insumo-history__empty">Nenhuma movimentacao disponivel.</span>
            @endif
        </div>
    </div>
</div>
