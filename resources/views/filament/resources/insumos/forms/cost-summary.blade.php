@php
    $valorConvertido = (float) ($summary['valor_convertido_brl'] ?? 0);
    $custoFinal = (float) ($summary['custo_nacionalizado'] ?? 0);
@endphp

<div class="insumo-cost-summary">
    <div class="insumo-cost-summary__grid">
        <div class="insumo-cost-summary__card">
            <span class="insumo-cost-summary__label">Valor convertido em real</span>
            <div class="insumo-cost-summary__output">
                R$ {{ number_format($valorConvertido, 2, ',', '.') }}
            </div>
            <p class="insumo-cost-summary__hint">Calculado automaticamente</p>
        </div>

        <div class="insumo-cost-summary__card">
            <span class="insumo-cost-summary__label">Fatores aplicados</span>

            <div class="insumo-cost-summary__factors">
                @if (($origem ?? 'nacional') !== 'importado')
                    <span class="insumo-cost-summary__empty">Aplicavel apenas a insumos importados.</span>
                @elseif (count($factorLines))
                    @foreach ($factorLines as $factor)
                        <div class="insumo-cost-summary__factor">
                            <div>
                                <strong>{{ $factor['nome'] }}</strong>
                                <span>{{ $factor['tipo'] }}</span>
                            </div>

                            <strong>{{ $factor['valor'] }}</strong>
                        </div>
                    @endforeach
                @else
                    <span class="insumo-cost-summary__empty">Nenhum fator adicional aplicado.</span>
                @endif
            </div>

            <p class="insumo-cost-summary__hint">Calculado automaticamente</p>
        </div>
    </div>

    <div class="insumo-cost-summary__final">
        <span class="insumo-cost-summary__label">Custo final nacionalizado</span>
        <div class="insumo-cost-summary__highlight">
            R$ {{ number_format($custoFinal, 2, ',', '.') }}
        </div>
        <p class="insumo-cost-summary__hint">Calculado automaticamente</p>
    </div>
</div>
