@php
    $summary = $summary ?? ['has_produto' => false, 'message' => 'Selecione um produto para ver o resumo financeiro.'];
@endphp

@include('filament.partials.stock-theme-styles')

<div class="insumo-mov-summary">
    @if (! ($summary['has_produto'] ?? false))
        <div class="insumo-mov-summary__empty">
            <span class="insumo-mov-summary__eyebrow">Resumo financeiro</span>
            <h3 class="insumo-mov-summary__title">Movimentacao de produto</h3>
            <p class="insumo-mov-summary__empty-text">{{ $summary['message'] ?? 'Selecione um produto para ver o resumo financeiro.' }}</p>
        </div>
    @else
        <div class="insumo-mov-summary__header">
            <div>
                <span class="insumo-mov-summary__eyebrow">Resumo financeiro</span>
                <h3 class="insumo-mov-summary__title">Movimentacao de produto</h3>
                <p class="insumo-mov-summary__description">
                    Tipo {{ strtolower($summary['tipo_label']) }} com base no custo atual de formacao do produto.
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
                <span class="insumo-mov-summary__stat-label">Custo base</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['custo_base'] }}</div>
            </article>

            <article class="insumo-mov-summary__stat">
                <span class="insumo-mov-summary__stat-label">Venda final sugerida</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['preco_sugerido'] }}</div>
            </article>

            <article class="insumo-mov-summary__stat">
                <span class="insumo-mov-summary__stat-label">Venda final</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['preco_tabela'] }}</div>
            </article>

            <article class="insumo-mov-summary__stat insumo-mov-summary__stat--highlight">
                <span class="insumo-mov-summary__stat-label">Venda minima</span>
                <div class="insumo-mov-summary__stat-value">{{ $summary['preco_minimo'] }}</div>
                <p class="insumo-mov-summary__stat-hint">Limite minimo para desconto ao cliente.</p>
            </article>
        </div>

        <div class="insumo-mov-summary__details">
            <section class="insumo-mov-summary__panel">
                <span class="insumo-mov-summary__panel-title">Contexto do produto</span>

                <div class="insumo-mov-summary__impact">
                    <div class="insumo-mov-summary__impact-row">
                        <span class="insumo-mov-summary__impact-label">Status atual</span>
                        <strong class="insumo-mov-summary__impact-value">{{ $summary['status'] }}</strong>
                    </div>

                    <div class="insumo-mov-summary__impact-row">
                        <span class="insumo-mov-summary__impact-label">Custo unitario aplicado</span>
                        <strong class="insumo-mov-summary__impact-value">{{ $summary['custo_base'] }}</strong>
                    </div>
                </div>
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
