<section class="section avoid-break">
    <h2 class="section-title">Observações e condições comerciais</h2>
    <div class="note-box">
        @if($pedido['observacao'])
            <div><strong>Observações:</strong> {{ $pedido['observacao'] }}</div>
        @endif
        @if($pedido['condicoes_comerciais'])
            <div><strong>Condições:</strong> {{ $pedido['condicoes_comerciais'] }}</div>
        @endif
        @if($pedido['frete'])
            <div><strong>Frete cobrado:</strong> {{ $pedido['frete'] }}</div>
        @endif
    </div>
</section>
