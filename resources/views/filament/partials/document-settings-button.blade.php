@php
    use App\Filament\Resources\DocumentoConfiguracoes\DocumentoConfiguracaoResource;
@endphp

@if (filament()->auth()->check() && DocumentoConfiguracaoResource::canAccess())
    <x-filament::icon-button
        tag="a"
        :href="DocumentoConfiguracaoResource::getUrl('index')"
        :spa-mode="true"
        color="gray"
        icon="heroicon-o-cog-6-tooth"
        icon-size="lg"
        label="Configurações de documentos"
        tooltip="Configurações de documentos"
        class="fi-topbar-document-settings-btn"
    />
@endif
