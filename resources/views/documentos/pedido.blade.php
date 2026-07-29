@extends('documentos.layout')

@push('styles')
<style>
    .product-name {
        font-weight: 700;
    }

    .item-note {
        color: #666;
        font-size: 7px;
        margin-top: 2px;
    }

    .client-grid td {
        width: 33.333%;
    }

    .address-grid td {
        width: 50%;
    }
</style>
@endpush

@section('content')
    @php
        $produtoPaginas = array_chunk($pedido['itens'], 10);
        $produtoPaginas = $produtoPaginas ?: [[]];
        $lotePaginas = array_chunk($pedido['lotes'], 10);
        $temLotes = count($lotePaginas) > 0;
        $temNotas = $pedido['observacao'] || $pedido['condicoes_comerciais'] || $pedido['frete'];
        $temFotos = count($pedido['fotos']) > 0;
    @endphp

    @foreach($produtoPaginas as $indice => $itens)
        @php
            $primeiraPagina = $indice === 0;
            $ultimaPaginaProdutos = $indice === count($produtoPaginas) - 1;
            $incluirNotas = $temNotas && $ultimaPaginaProdutos && ! $temLotes;
            $ultimaPaginaDocumento = $ultimaPaginaProdutos && ! $temLotes && ! $temFotos;
        @endphp
        <div class="pdf-page {{ $ultimaPaginaDocumento ? 'pdf-page-last' : '' }}">
            @if($primeiraPagina)
                <section class="section">
                    <h2 class="section-title">Cliente</h2>
                    <table class="info-grid client-grid">
                        <tr>
                            <td>
                                <span class="label">Nome / Razão social</span>
                                <span class="value">{{ $pedido['cliente']['nome'] }}</span>
                            </td>
                            <td>
                                <span class="label">CPF / CNPJ</span>
                                <span class="value">{{ $pedido['cliente']['documento'] ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="label">Telefone</span>
                                <span class="value">{{ $pedido['cliente']['telefone'] ?: '-' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="label">E-mail</span>
                                <span class="value">{{ $pedido['cliente']['email'] ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="label">Vendedor</span>
                                <span class="value">{{ $pedido['vendedor'] ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="label">Pagamento</span>
                                <span class="value">{{ $pedido['cliente']['pagamento'] ?: '-' }}</span>
                            </td>
                        </tr>
                    </table>
                </section>

                <section class="section">
                    <h2 class="section-title">Endereços</h2>
                    <table class="address-grid">
                        <tr>
                            <td>
                                <span class="label">Endereço principal</span>
                                @forelse($pedido['endereco_principal'] as $linha)
                                    <div class="value">{{ $linha }}</div>
                                @empty
                                    <div class="value muted">Não informado</div>
                                @endforelse
                            </td>
                            <td>
                                <span class="label">Endereço de entrega</span>
                                @forelse($pedido['endereco_entrega'] as $linha)
                                    <div class="value">{{ $linha }}</div>
                                @empty
                                    <div class="value muted">Não informado</div>
                                @endforelse
                            </td>
                        </tr>
                    </table>
                </section>
            @endif

            @if(count($itens) > 0)
                <section class="section">
                    <h2 class="section-title">
                        Produtos{{ $indice > 0 ? ' - continuação' : '' }}
                    </h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 45%">Produto</th>
                                <th class="text-right" style="width: 12%">Qtd.</th>
                                <th class="text-center" style="width: 8%">Un.</th>
                                <th class="text-right" style="width: 17%">Vlr. unit.</th>
                                <th class="text-right" style="width: 18%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($itens as $item)
                                <tr>
                                    <td>
                                        <div class="product-name">{{ $item['produto'] }}</div>
                                        @if($item['observacao'])
                                            <div class="item-note">{{ $item['observacao'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right nowrap">{{ $item['quantidade'] }}</td>
                                    <td class="text-center nowrap">{{ $item['unidade'] }}</td>
                                    <td class="text-right nowrap">{{ $item['valor_unitario'] }}</td>
                                    <td class="text-right nowrap"><strong>{{ $item['subtotal'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($ultimaPaginaProdutos)
                        <div class="total-box">
                            Total do pedido <strong>{{ $pedido['total'] }}</strong>
                        </div>
                    @endif
                </section>
            @endif

            @if($incluirNotas)
                @include('documentos.partials.notas-pedido', ['pedido' => $pedido])
            @endif
        </div>
    @endforeach

    @foreach($lotePaginas as $indice => $lotes)
        @php
            $ultimaPaginaLotes = $indice === count($lotePaginas) - 1;
            $ultimaPaginaDocumento = $ultimaPaginaLotes && ! $temFotos;
        @endphp
        <div class="pdf-page {{ $ultimaPaginaDocumento ? 'pdf-page-last' : '' }}">
            <section class="section">
                <h2 class="section-title">
                    Lotes, fabricação e validade{{ $indice > 0 ? ' - continuação' : '' }}
                </h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 33%">Produto</th>
                            <th style="width: 18%">Lote</th>
                            <th class="text-right" style="width: 13%">Qtd.</th>
                            <th style="width: 18%">Fabricação</th>
                            <th style="width: 18%">Validade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lotes as $lote)
                            <tr>
                                <td>{{ $lote['produto'] }}</td>
                                <td>{{ $lote['lote'] }}</td>
                                <td class="text-right nowrap">{{ $lote['quantidade'] }}</td>
                                <td class="nowrap">{{ $lote['fabricacao'] ?: '-' }}</td>
                                <td class="nowrap">{{ $lote['validade'] ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            @if($temNotas && $ultimaPaginaLotes)
                @include('documentos.partials.notas-pedido', ['pedido' => $pedido])
            @endif
        </div>
    @endforeach

    @if($temFotos)
        @include('documentos.partials.fotos', ['fotos' => $pedido['fotos']])
    @endif
@endsection
