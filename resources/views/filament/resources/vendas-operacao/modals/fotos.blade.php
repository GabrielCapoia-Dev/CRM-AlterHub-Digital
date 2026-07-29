<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($pedido->fotos as $foto)
        <article class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <a href="{{ route('documentos.pedidos.fotos.visualizar', ['pedido' => $pedido, 'foto' => $foto]) }}" target="_blank" rel="noopener">
                <img
                    src="{{ route('documentos.pedidos.fotos.visualizar', ['pedido' => $pedido, 'foto' => $foto]) }}"
                    alt="{{ $foto->descricao ?: $foto->nome_original }}"
                    class="h-52 w-full object-contain bg-gray-50 dark:bg-gray-950"
                    loading="lazy"
                >
            </a>
            <div class="space-y-2 p-4 text-sm">
                <div class="font-medium">{{ $foto->descricao ?: $foto->nome_original }}</div>
                <div class="text-gray-500">
                    Produto: {{ $foto->item?->produto_nome_snapshot ?: 'Pedido inteiro' }}<br>
                    Enviado por {{ $foto->user?->name ?: '-' }} em {{ $foto->created_at?->format('d/m/Y H:i') }}
                </div>
                <a
                    href="{{ route('documentos.pedidos.fotos.baixar', ['pedido' => $pedido, 'foto' => $foto]) }}"
                    class="font-medium text-primary-600 hover:underline"
                >Baixar original</a>
            </div>
        </article>
    @empty
        <p class="text-sm text-gray-500">Nenhuma foto vinculada a este pedido.</p>
    @endforelse
</div>
