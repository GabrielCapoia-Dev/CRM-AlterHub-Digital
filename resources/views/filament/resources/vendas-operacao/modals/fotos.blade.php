<div class="oa-photo-gallery">
    @forelse ($pedido->fotos as $foto)
        <article class="oa-photo-card">
            <a
                class="oa-photo-card__preview"
                href="{{ route('documentos.pedidos.fotos.visualizar', ['pedido' => $pedido, 'foto' => $foto]) }}"
                target="_blank"
                rel="noopener"
                title="Abrir imagem original em uma nova aba"
            >
                <img
                    src="{{ route('documentos.pedidos.fotos.visualizar', ['pedido' => $pedido, 'foto' => $foto]) }}"
                    alt="{{ $foto->descricao ?: $foto->nome_original }}"
                    class="oa-photo-card__image"
                    loading="lazy"
                >
                <span class="oa-photo-card__hint">Clique para abrir no tamanho original</span>
            </a>
            <div class="oa-photo-card__details">
                <div class="oa-photo-card__copy">
                    <strong class="oa-photo-card__title">{{ $foto->descricao ?: $foto->nome_original }}</strong>
                    <span class="oa-photo-card__meta">
                        Produto: {{ $foto->item?->produto_nome_snapshot ?: 'Pedido inteiro' }}
                    </span>
                    <span class="oa-photo-card__meta">
                        Enviado por {{ $foto->user?->name ?: '-' }} em {{ $foto->created_at?->format('d/m/Y H:i') }}
                    </span>
                </div>
                <a
                    href="{{ route('documentos.pedidos.fotos.baixar', ['pedido' => $pedido, 'foto' => $foto]) }}"
                    class="oa-photo-card__download"
                >Baixar original</a>
            </div>
        </article>
    @empty
        <div class="oa-photo-gallery__empty">Nenhuma foto vinculada a este pedido.</div>
    @endforelse
</div>
