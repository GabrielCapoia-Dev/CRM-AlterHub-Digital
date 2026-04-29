<x-filament-panels::page>
    <div class="crm-resource-page">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Estoque</p>
                    <h2 class="crm-resource-hero__title">Movimentacoes de produtos</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Historico consolidado das movimentacoes que alteram o saldo dos produtos.
                </p>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
