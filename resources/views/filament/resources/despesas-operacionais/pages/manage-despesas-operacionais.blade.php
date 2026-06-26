<x-filament-panels::page>
    @include('filament.partials.stock-theme-styles')

    <div class="crm-resource-page">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Operacao</p>
                    <h2 class="crm-resource-hero__title">Despesas</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Lancamentos operacionais e tributarios usados no resultado, na visao consolidada e no BI.
                </p>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
