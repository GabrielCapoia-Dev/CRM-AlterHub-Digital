@php
    $linhas = $pedido->vendasOperacao;
    $itensCount = $linhas->count();
    $quantidadeTotal = $linhas->sum(fn ($linha): float => (float) $linha->quantidade);
@endphp

<div class="oa-sale-products">
    <section class="oa-sale-products__summary" aria-label="Resumo da venda">
        <article class="oa-sale-products__card">
            <span class="oa-sale-products__label">Venda</span>
            <strong class="oa-sale-products__value">{{ $pedido->codigo }}</strong>
            <small class="oa-sale-products__hint">{{ $itensCount }} item(ns)</small>
        </article>

        <article class="oa-sale-products__card">
            <span class="oa-sale-products__label">Cliente</span>
            <strong class="oa-sale-products__value">{{ $pedido->cliente_nome_snapshot ?: '-' }}</strong>
            <small class="oa-sale-products__hint">{{ $pedido->vendedor_nome_snapshot ?: 'Sem vendedor informado' }}</small>
        </article>

        <article class="oa-sale-products__card oa-sale-products__card--highlight">
            <span class="oa-sale-products__label">Receita</span>
            <strong class="oa-sale-products__value">R$ {{ number_format((float) $pedido->receita_bruta_total, 2, ',', '.') }}</strong>
            <small class="oa-sale-products__hint">Quantidade total: {{ number_format($quantidadeTotal, 4, ',', '.') }}</small>
        </article>
    </section>

    <section class="oa-sale-products__table-card">
        <header class="oa-sale-products__table-header">
            <div>
                <h3>Itens da venda</h3>
                <p>Produtos, valores e movimentacoes vinculadas ao estoque.</p>
            </div>

            <span>{{ $itensCount }} item(ns)</span>
        </header>

        <div class="oa-sale-products__table-wrap">
            <table class="oa-sale-products__table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="is-num">Qtd.</th>
                        <th class="is-num">Preco un.</th>
                        <th class="is-num">Total</th>
                        <th>Movimentacao</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($linhas as $linha)
                        <tr>
                            <td data-label="Produto">
                                <strong>{{ $linha->produto_nome_snapshot }}</strong>
                                <span class="oa-sale-products__code">{{ $linha->produto_codigo_snapshot ?: 'Sem codigo' }}</span>
                            </td>
                            <td data-label="Qtd." class="is-num">
                                {{ number_format((float) $linha->quantidade, 4, ',', '.') }} {{ $linha->unidade_snapshot }}
                            </td>
                            <td data-label="Preco un." class="is-num">
                                R$ {{ number_format((float) $linha->preco_unitario, 2, ',', '.') }}
                            </td>
                            <td data-label="Total" class="is-num">
                                <strong>R$ {{ number_format((float) $linha->receita_bruta, 2, ',', '.') }}</strong>
                            </td>
                            <td data-label="Movimentacao">
                                @if ($linha->produtoMovimentacao?->documento_referencia)
                                    <span class="oa-sale-products__badge">{{ $linha->produtoMovimentacao->documento_referencia }}</span>
                                @else
                                    <span class="oa-sale-products__muted">Sem movimentacao</span>
                                @endif

                                @if ($linha->produtoMovimentacao)
                                    <small>Saldo: {{ number_format((float) $linha->produtoMovimentacao->saldo_atual, 4, ',', '.') }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="oa-sale-products__empty-row">
                            <td colspan="5">
                                <div class="oa-sale-products__empty">
                                    <strong>Nenhum produto registrado nesta venda.</strong>
                                    <span>Quando houver itens vinculados, eles aparecem aqui com quantidade, valor e movimentacao.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
