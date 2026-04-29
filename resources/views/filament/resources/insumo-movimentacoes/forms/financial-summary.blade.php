@php
    $summary = $summary ?? ['has_insumo' => false, 'message' => 'Selecione um insumo para ver o resumo financeiro.'];
@endphp

@include('filament.partials.stock-theme-styles')

<div class="insumo-mov-summary">
    @if (! ($summary['has_insumo'] ?? false))
        <div class="insumo-mov-summary__empty">
            <span class="insumo-mov-summary__eyebrow">Resumo financeiro</span>
            <h3 class="insumo-mov-summary__title">Movimentacao de insumo</h3>
            <p class="insumo-mov-summary__empty-text">{{ $summary['message'] ?? 'Selecione um insumo para ver o resumo financeiro.' }}</p>
        </div>
    @else
        <div class="insumo-mov-summary__header">
            <div>
                <span class="insumo-mov-summary__eyebrow">Resumo financeiro</span>
                <h3 class="insumo-mov-summary__title">Movimentacao de insumo</h3>
                <p class="insumo-mov-summary__description">
                    Tipo {{ strtolower($summary['tipo_label']) }} com base no custo atual do insumo.
                </p>
            </div>

            <div class="insumo-mov-summary__snapshot">
                <div class="insumo-mov-summary__snapshot-item">
                    <span class="insumo-mov-summary__snapshot-label">Estoque atual</span>
                    <strong class="insumo-mov-summary__snapshot-value">{{ $summary['estoque_atual'] }}</strong>
                </div>

                <div class="insumo-mov-summary__snapshot-item">
                    <span class="insumo-mov-summary__snapshot-label">Quantidade informada</span>
                    <strong class="insumo-mov-summary__snapshot-value">{{ $summary['quantidade'] }}</strong>
                </div>
            </div>
        </div>

        <div class="insumo-mov-summary__stats">
            <article class="insumo-mov-summary__stat">
                <span class="insumo-mov-summary__stat-label">Valor na origem</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['valor_origem'] }}</div>
            </article>

            <article class="insumo-mov-summary__stat">
                <span class="insumo-mov-summary__stat-label">Cambio</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['cambio'] }}</div>
            </article>

            <article class="insumo-mov-summary__stat">
                <span class="insumo-mov-summary__stat-label">Custo efetivo</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['custo_efetivo'] }}</div>
                <p class="insumo-mov-summary__stat-hint">Valor convertido antes do custo final nacionalizado.</p>
            </article>

            <article class="insumo-mov-summary__stat insumo-mov-summary__stat--highlight">
                <span class="insumo-mov-summary__stat-label">Custo final real</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['custo_final'] }}</div>
                <p class="insumo-mov-summary__stat-hint">Valor unitario usado para o impacto financeiro.</p>
            </article>
        </div>

        <div class="insumo-mov-summary__details">
            <section class="insumo-mov-summary__panel">
                <span class="insumo-mov-summary__panel-title">Fatores de importacao</span>

                @if (($summary['origem'] ?? 'nacional') !== 'importado')
                    <p class="insumo-mov-summary__panel-empty">Nao se aplica a insumos nacionais.</p>
                @elseif (blank($summary['fatores'] ?? []))
                    <p class="insumo-mov-summary__panel-empty">Nenhum fator adicional cadastrado para este insumo.</p>
                @else
                    <div class="insumo-mov-summary__panel-body">
                        @foreach (($summary['fatores'] ?? []) as $factor)
                            <article class="insumo-mov-summary__factor">
                                <div class="insumo-mov-summary__factor-copy">
                                    <h4 class="insumo-mov-summary__factor-name">{{ $factor['nome'] }}</h4>
                                    <p class="insumo-mov-summary__factor-type">{{ $factor['tipo'] }}</p>
                                </div>

                                <div class="insumo-mov-summary__factor-value">{{ $factor['valor'] }}</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="insumo-mov-summary__panel">
                <span class="insumo-mov-summary__panel-title">Impacto financeiro</span>

                <div class="insumo-mov-summary__impact">
                    <div class="insumo-mov-summary__impact-row">
                        <span class="insumo-mov-summary__impact-label">Quantidade considerada financeiramente</span>
                        <strong class="insumo-mov-summary__impact-value">{{ $summary['quantidade_financeira'] }}</strong>
                    </div>

                    <div class="insumo-mov-summary__impact-row">
                        <span class="insumo-mov-summary__impact-label">Formula</span>
                        <strong class="insumo-mov-summary__impact-value">{{ $summary['impacto_formula'] }}</strong>
                    </div>

                    <div class="insumo-mov-summary__impact-total">
                        <span class="insumo-mov-summary__impact-label">Valor total final</span>
                        <strong class="insumo-mov-summary__impact-total-value">{{ $summary['impacto_total'] }}</strong>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
