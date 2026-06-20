@php
    $linhas = $pedido->vendasOperacao;
@endphp

<div class="space-y-4">
    <section class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase text-gray-500">Venda</p>
            <strong class="text-sm text-gray-950">{{ $pedido->codigo }}</strong>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase text-gray-500">Cliente</p>
            <strong class="text-sm text-gray-950">{{ $pedido->cliente_nome_snapshot ?: '-' }}</strong>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase text-gray-500">Receita</p>
            <strong class="text-sm text-gray-950">R$ {{ number_format((float) $pedido->receita_bruta_total, 2, ',', '.') }}</strong>
        </div>
    </section>

    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                <tr>
                    <th class="px-3 py-2">Produto</th>
                    <th class="px-3 py-2 text-right">Qtd.</th>
                    <th class="px-3 py-2 text-right">Preco un.</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2">Movimentacao</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($linhas as $linha)
                    <tr>
                        <td class="px-3 py-3">
                            <strong class="block text-gray-950">{{ $linha->produto_nome_snapshot }}</strong>
                            <span class="text-xs text-gray-500">{{ $linha->produto_codigo_snapshot ?: 'Sem codigo' }}</span>
                        </td>
                        <td class="px-3 py-3 text-right">
                            {{ number_format((float) $linha->quantidade, 4, ',', '.') }} {{ $linha->unidade_snapshot }}
                        </td>
                        <td class="px-3 py-3 text-right">
                            R$ {{ number_format((float) $linha->preco_unitario, 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-right">
                            R$ {{ number_format((float) $linha->receita_bruta, 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-3">
                            {{ $linha->produtoMovimentacao?->documento_referencia ?: '-' }}
                            @if ($linha->produtoMovimentacao)
                                <span class="block text-xs text-gray-500">
                                    Saldo: {{ number_format((float) $linha->produtoMovimentacao->saldo_atual, 4, ',', '.') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                            Nenhum produto registrado nesta venda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
