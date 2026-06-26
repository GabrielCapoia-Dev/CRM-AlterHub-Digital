<x-filament-panels::page>
    @include('filament.partials.stock-theme-styles')

    <div class="crm-resource-page">
        <section class="crm-resource-hero">
            <div class="crm-resource-hero__inner">
                <div class="crm-resource-hero__content">
                    <p class="crm-resource-hero__eyebrow">Operacao</p>
                    <h2 class="crm-resource-hero__title">Vendas</h2>
                </div>

                <p class="crm-resource-hero__description">
                    Vendas agrupadas por cliente e oportunidade, com produtos e baixas de estoque vinculadas.
                </p>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
