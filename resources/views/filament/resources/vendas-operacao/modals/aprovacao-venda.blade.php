@php
    $approvalReasons = isset($approvalReasons) && is_array($approvalReasons)
        ? $approvalReasons
        : (is_array($pedido->motivos_aprovacao) ? $pedido->motivos_aprovacao : []);
    $stockShortages = collect($approvalReasons['estoque'] ?? [])
        ->filter(fn ($shortage): bool => is_array($shortage))
        ->values();
    $discountMessage = trim((string) data_get($approvalReasons, 'desconto.mensagem', ''));
    $alertCount = $stockShortages->count() + ($discountMessage !== '' ? 1 : 0);
@endphp

<div class="oa-approval-review">
    <section class="oa-approval-review__summary">
        <span class="oa-approval-review__summary-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-clipboard-document-check" />
        </span>

        <div>
            <p class="oa-approval-review__eyebrow">Revisão necessária</p>
            <h3>{{ $alertCount }} {{ $alertCount === 1 ? 'alerta identificado' : 'alertas identificados' }}</h3>
            <p>Confira os valores abaixo antes de aprovar a saída desta venda.</p>
        </div>
    </section>

    @if ($discountMessage !== '')
        <section class="oa-approval-review__discount">
            <span class="oa-approval-review__alert-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-receipt-percent" />
            </span>

            <div>
                <strong>Condição comercial</strong>
                <p>{{ $discountMessage }}</p>
            </div>
        </section>
    @endif

    @if ($stockShortages->isNotEmpty())
        <section class="oa-approval-review__stock">
            <header>
                <div>
                    <p class="oa-approval-review__eyebrow">Validação de estoque</p>
                    <h4>Produtos com quantidade insuficiente</h4>
                </div>

                <span>{{ $stockShortages->count() }} {{ $stockShortages->count() === 1 ? 'produto' : 'produtos' }}</span>
            </header>

            <div class="oa-approval-review__items">
                @foreach ($stockShortages as $shortage)
                    @php
                        $productName = trim((string) ($shortage['produto'] ?? ''));

                        if ($productName === '') {
                            $productName = 'Produto #'.($shortage['produto_id'] ?? 'não informado');
                        }
                    @endphp

                    <article class="oa-approval-review__item">
                        <h5>{{ $productName }}</h5>

                        <dl class="oa-approval-review__metrics">
                            <div>
                                <dt>Solicitado</dt>
                                <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['solicitado'] ?? 0, 4) }}</dd>
                            </div>

                            <div>
                                <dt>Disponível</dt>
                                <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['disponivel'] ?? 0, 4) }}</dd>
                            </div>

                            <div class="oa-approval-review__metric--danger">
                                <dt>Déficit</dt>
                                <dd>{{ \App\Support\Ui\NumericFormat::decimal($shortage['deficit'] ?? 0, 4) }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($alertCount === 0)
        <section class="oa-approval-review__empty">
            Nenhum motivo detalhado foi registrado para esta aprovação.
        </section>
    @endif

    <aside class="oa-approval-review__notice">
        <x-filament::icon icon="heroicon-o-information-circle" aria-hidden="true" />
        <p>A aprovação somente será concluída se houver estoque suficiente no momento da confirmação.</p>
    </aside>
</div>
