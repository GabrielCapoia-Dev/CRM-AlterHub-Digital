@extends('documentos.layout')

@push('styles')
<style>
    .operational-notice {
        background: #eef3fb;
        border-left: 3px solid #2455a4;
        color: #27405f;
        margin-bottom: 12px;
        padding: 6px 9px;
    }

    .client-heading {
        border-bottom: 2px solid #2455a4;
        color: #1f2937;
        font-size: 12px;
        margin: 14px 0 7px;
        padding-bottom: 4px;
    }

    .client-document {
        color: #666;
        font-size: 8px;
        font-weight: 400;
        margin-left: 7px;
    }

    .order-block {
        margin-bottom: 15px;
    }

    .order-header {
        background: #f5f6f7;
        border: 1px solid #c9cdd2;
        padding: 7px;
        page-break-after: avoid;
    }

    .order-title {
        font-size: 10px;
        font-weight: 700;
    }

    .order-meta {
        color: #555;
        font-size: 7.5px;
        margin-top: 3px;
    }

    .order-meta span {
        margin-right: 12px;
    }

    .delivery-address {
        border-right: 1px solid #d5d8dc;
        display: inline-block;
        margin-right: 10px;
        padding-right: 10px;
        width: 53%;
    }

    .load-data {
        display: inline-block;
        vertical-align: top;
        width: 40%;
    }

    .lot-line {
        font-size: 7px;
        margin-bottom: 2px;
    }

    .romaneio-summary {
        background: #f6f7f8;
        border: 1px solid #c9cdd2;
        margin-top: 14px;
        padding: 10px;
    }

    .summary-value {
        display: block;
        font-size: 13px;
        font-weight: 700;
        margin-top: 2px;
    }

    .cancelled-warning {
        background: #fff2f2;
        border: 1px solid #c94a4a;
        color: #8e2525;
        font-weight: 700;
        margin-bottom: 10px;
        padding: 7px;
        text-align: center;
    }
</style>
@endpush

@section('content')
    @php($primeiroBloco = true)
    @foreach($romaneio['grupos'] as $grupo)
        @foreach($grupo['pedidos'] as $pedido)
            @foreach(array_chunk($pedido['itens'], 8) as $indice => $itens)
                <div class="pdf-page">
                    @if($primeiroBloco)
                        @if($romaneio['status'] === 'Cancelado')
                            <div class="cancelled-warning">ROMANEIO CANCELADO - mantido apenas para histórico e auditoria.</div>
                        @endif

                        <div class="operational-notice">
                            Documento operacional de separação e carga. Não possui valor fiscal.
                        </div>
                    @endif
                    <section>
                        <h2 class="client-heading">
                            {{ $grupo['cliente']['nome'] }}
                            @if($grupo['cliente']['documento'])
                                <span class="client-document">{{ $grupo['cliente']['documento'] }}</span>
                            @endif
                        </h2>

                    <div class="order-block">
                        <div class="order-header">
                            <div class="order-title">
                                Pedido {{ $pedido['numero'] }}{{ $indice > 0 ? ' — continuação' : '' }}
                            </div>
                            <div class="order-meta">
                                <span>Data: {{ $pedido['data'] ?: '-' }}</span>
                                <span>Vendedor: {{ $pedido['vendedor'] ?: '-' }}</span>
                                <span>Pagamento: {{ $pedido['pagamento'] ?: '-' }}</span>
                            </div>
                            <div class="order-meta">
                                <span>Telefone: {{ $pedido['cliente']['telefone'] ?: '-' }}</span>
                                <span>E-mail: {{ $pedido['cliente']['email'] ?: '-' }}</span>
                            </div>
                            <div class="order-meta">
                                <div class="delivery-address">
                                    <strong>Entrega:</strong>
                                    @forelse($pedido['endereco_entrega'] as $linha)
                                        {{ $linha }}@if(! $loop->last) - @endif
                                    @empty
                                        Não informada
                                    @endforelse
                                </div>
                                <div class="load-data">
                                    <strong>Volumes:</strong> {{ $pedido['volumes'] }}
                                    &nbsp;&nbsp;
                                    <strong>Peso:</strong> {{ $pedido['peso'] }}
                                </div>
                            </div>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: {{ $romaneio['exibir_valores'] ? '25%' : '35%' }}">Produto</th>
                                    <th class="text-right" style="width: {{ $romaneio['exibir_valores'] ? '9%' : '12%' }}">Qtd.</th>
                                    <th class="text-center" style="width: {{ $romaneio['exibir_valores'] ? '7%' : '8%' }}">Un.</th>
                                    @if($romaneio['exibir_valores'])
                                        <th class="text-right" style="width: 14%">Vlr. unit.</th>
                                        <th class="text-right" style="width: 14%">Subtotal</th>
                                    @endif
                                    <th class="text-right" style="width: {{ $romaneio['exibir_valores'] ? '12%' : '15%' }}">Peso</th>
                                    <th style="width: {{ $romaneio['exibir_valores'] ? '19%' : '30%' }}">Lote / validade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($itens as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item['produto'] }}</strong>
                                            @if($item['observacao'])
                                                <div class="small muted">{{ $item['observacao'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right nowrap">{{ $item['quantidade'] }}</td>
                                        <td class="text-center nowrap">{{ $item['unidade'] }}</td>
                                        @if($romaneio['exibir_valores'])
                                            <td class="text-right nowrap">{{ $item['valor_unitario'] }}</td>
                                            <td class="text-right nowrap">{{ $item['subtotal'] }}</td>
                                        @endif
                                        <td class="text-right nowrap">{{ $item['peso'] }}</td>
                                        <td>
                                            @forelse($item['lotes'] as $lote)
                                                <div class="lot-line">
                                                    {{ $lote['lote'] ?: 'Lote não informado' }}
                                                    @if($lote['validade'])
                                                        - val. {{ $lote['validade'] }}
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="muted">-</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($indice === count(array_chunk($pedido['itens'], 8)) - 1)
                            @if($romaneio['exibir_valores'])
                                <div class="small text-right" style="margin-top: 4px;">
                                    <strong>Total comercial do pedido:</strong> {{ $pedido['valor'] }}
                                </div>
                            @endif

                            @if($pedido['observacao'] || $pedido['condicoes_comerciais'])
                                <div class="small" style="margin-top: 5px;">
                                    @if($pedido['observacao'])
                                        <strong>Observações:</strong> {{ $pedido['observacao'] }}
                                    @endif
                                    @if($pedido['condicoes_comerciais'])
                                        <br><strong>Condições:</strong> {{ $pedido['condicoes_comerciais'] }}
                                    @endif
                                </div>
                            @endif
                        @endif
                        </div>
                    </section>
                </div>
                @php($primeiroBloco = false)
            @endforeach
        @endforeach
    @endforeach

    <div class="pdf-page pdf-page-last">
        @if($primeiroBloco)
            @if($romaneio['status'] === 'Cancelado')
                <div class="cancelled-warning">ROMANEIO CANCELADO - mantido apenas para histórico e auditoria.</div>
            @endif

            <div class="operational-notice">
                Documento operacional de separação e carga. Não possui valor fiscal.
            </div>
        @endif
        <section class="romaneio-summary avoid-break">
            <h2 class="section-title">Totalização da carga</h2>
            <table class="summary-grid">
                <tr>
                    <td>
                        <span class="label">Pedidos</span>
                        <span class="summary-value">{{ $romaneio['totais']['pedidos'] }}</span>
                    </td>
                    <td>
                        <span class="label">Clientes</span>
                        <span class="summary-value">{{ $romaneio['totais']['clientes'] }}</span>
                    </td>
                    <td>
                        <span class="label">Itens</span>
                        <span class="summary-value">{{ $romaneio['totais']['itens'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">Quantidade</span>
                        <span class="summary-value">{{ $romaneio['totais']['quantidade'] }}</span>
                    </td>
                    <td>
                        <span class="label">Volumes</span>
                        <span class="summary-value">{{ $romaneio['totais']['volumes'] }}</span>
                    </td>
                    <td>
                        <span class="label">Peso total</span>
                        <span class="summary-value">{{ $romaneio['totais']['peso'] }}</span>
                    </td>
                </tr>
                @if($romaneio['exibir_valores'])
                    <tr>
                        <td colspan="3">
                            <span class="label">Valor comercial total</span>
                            <span class="summary-value">{{ $romaneio['totais']['valor'] }}</span>
                        </td>
                    </tr>
                @endif
            </table>

            <div class="small" style="margin-top: 7px;">
                <strong>Gerado originalmente em:</strong> {{ $romaneio['gerado_em'] ?: '-' }}
                &nbsp;&nbsp;
                <strong>Responsável:</strong> {{ $romaneio['usuario_responsavel'] ?: '-' }}
            </div>

            @if($romaneio['observacao'])
                <div class="small" style="margin-top: 5px;">
                    <strong>Observações gerais:</strong> {{ $romaneio['observacao'] }}
                </div>
            @endif
        </section>
    </div>
@endsection
