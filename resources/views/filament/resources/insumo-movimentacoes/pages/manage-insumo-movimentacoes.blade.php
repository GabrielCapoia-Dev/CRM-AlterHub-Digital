<x-filament-panels::page>
    @include('filament.partials.stock-theme-styles')

    <div class="crm-resource-page">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Estoque</p>
                    <h2 class="crm-resource-hero__title">Movimentacoes de insumos</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Historico geral das entradas, saidas, ajustes e transferencias dos insumos.
                </p>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
