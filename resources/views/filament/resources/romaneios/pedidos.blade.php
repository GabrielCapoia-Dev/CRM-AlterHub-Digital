<div class="space-y-5 oa-orders-view">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 oa-orders-view__summary">
        <div><strong>Status:</strong> {{ $romaneio->status === 'cancelado' ? 'Cancelado' : 'Ativo' }}</div>
        <div><strong>Responsavel:</strong> {{ $romaneio->user?->name ?? '-' }}</div>
        <div><strong>Volumes:</strong> {{ $romaneio->quantidade_volumes_total }}</div>
        <div><strong>Peso:</strong> {{ number_format((float) $romaneio->peso_total_kg, 4, ',', '.') }} kg</div>
    </div>

    @if ($romaneio->status === 'cancelado')
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
            <strong>Cancelado por:</strong> {{ $romaneio->canceladoPor?->name ?? '-' }}<br>
            <strong>Justificativa:</strong> {{ $romaneio->justificativa_cancelamento }}
        </div>
    @endif

    @foreach ($romaneio->pedidos as $pedido)
        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700 oa-orders-view__order">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 oa-orders-view__order-header">
                <div>
                    <div class="font-semibold">{{ $pedido->pedido_codigo_snapshot }} - {{ $pedido->cliente_nome_snapshot }}</div>
                    <div class="text-sm text-gray-500">{{ $pedido->vendedor_nome_snapshot ?: '-' }} · {{ $pedido->pedido_data_snapshot?->format('d/m/Y') }}</div>
                </div>
                <div class="text-sm">{{ $pedido->quantidade_volumes }} volume(s) · {{ number_format((float) $pedido->peso_total_kg, 4, ',', '.') }} kg</div>
            </div>

            <div class="overflow-x-auto oa-orders-view__table-wrap">
                <table class="w-full text-left text-sm oa-orders-view__table">
                    <thead><tr><th class="py-2">Produto</th><th>Qtd.</th><th>Peso</th><th>Lotes</th></tr></thead>
                    <tbody>
                    @foreach ($pedido->itens as $item)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $item->produto_nome_snapshot }}</td>
                            <td>{{ number_format((float) $item->quantidade, 4, ',', '.') }} {{ $item->unidade_snapshot }}</td>
                            <td>{{ number_format((float) $item->peso_total_kg, 4, ',', '.') }} kg</td>
                            <td>{{ collect($item->lotes_snapshot ?? [])->pluck('numero_lote')->filter()->implode(', ') ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
