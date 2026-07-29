@php
    $approvalReasons = is_array($pedido->motivos_aprovacao) ? $pedido->motivos_aprovacao : [];
    $stockShortages = collect($approvalReasons['estoque'] ?? [])
        ->filter(fn ($shortage): bool => is_array($shortage))
        ->values();
    $discountMessage = trim((string) data_get($approvalReasons, 'desconto.mensagem', ''));
@endphp

<div class="oa-approval-reasons">
    @if ($discountMessage !== '')
        <div class="oa-approval-reasons__commercial">
            <span class="oa-approval-reasons__commercial-icon" aria-hidden="true">%</span>

            <div>
                <span>Condição comercial</span>
                <strong>{{ $discountMessage }}</strong>
            </div>
        </div>
    @endif

    @if ($stockShortages->isNotEmpty())
        <div class="oa-approval-reasons__summary">
            <span class="oa-approval-reasons__summary-icon" aria-hidden="true">!</span>
            <strong>
                {{ $stockShortages->count() }}
                {{ $stockShortages->count() === 1 ? 'produto com estoque insuficiente' : 'produtos com estoque insuficiente' }}
            </strong>
        </div>

        <div class="oa-approval-reasons__list" role="list">
            @foreach ($stockShortages as $shortage)
                @php
                    $productName = trim((string) ($shortage['produto'] ?? ''));

                    if ($productName === '') {
                        $productName = 'Produto #'.($shortage['produto_id'] ?? 'não informado');
                    }
                @endphp

                <article class="oa-approval-reasons__item" role="listitem">
                    <div class="oa-approval-reasons__product">
                        <span class="oa-approval-reasons__product-marker" aria-hidden="true"></span>
                        <strong>{{ $productName }}</strong>
                    </div>

                    <dl class="oa-approval-reasons__metrics">
                        <div class="oa-approval-reasons__metric oa-approval-reasons__metric--requested">
                            <dt>Solicitado</dt>
                            <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['solicitado'] ?? 0, 4) }}</dd>
                        </div>

                        <div class="oa-approval-reasons__metric oa-approval-reasons__metric--available">
                            <dt>Disponível</dt>
                            <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['disponivel'] ?? 0, 4) }}</dd>
                        </div>

                        <div class="oa-approval-reasons__metric oa-approval-reasons__metric--deficit">
                            <dt>Déficit</dt>
                            <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['deficit'] ?? 0, 4) }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    @endif
</div>
