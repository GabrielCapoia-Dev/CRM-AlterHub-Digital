@foreach(array_chunk($fotos, 4) as $pagina => $fotosPagina)
    <div class="pdf-page {{ $loop->last ? 'pdf-page-last' : '' }}">
        <section>
            <h2 class="section-title">
                Anexo fotográfico da mercadoria separada{{ $pagina > 0 ? ' — continuação' : '' }}
            </h2>

            @foreach(array_chunk($fotosPagina, 2) as $linha)
                <table class="photo-grid avoid-break">
                    <tr>
                        @foreach($linha as $foto)
                            <td style="width: 50%; padding: 5px; vertical-align: top;">
                                <div style="border: 1px solid #d5d8dc; padding: 7px;">
                                    <div style="height: 220px; text-align: center;">
                                        <img
                                            src="{{ $foto['data_uri'] }}"
                                            alt="Mercadoria separada"
                                            style="max-width: 100%; max-height: 210px; width: auto; height: auto;"
                                        >
                                    </div>
                                    @if($foto['produto'])
                                        <div class="small"><strong>Produto:</strong> {{ $foto['produto'] }}</div>
                                    @endif
                                    @if($foto['descricao'])
                                        <div class="small"><strong>Descrição:</strong> {{ $foto['descricao'] }}</div>
                                    @endif
                                    <div class="small muted">
                                        Enviada em {{ $foto['enviado_em'] ?: '-' }}
                                        @if($foto['enviado_por'])
                                            por {{ $foto['enviado_por'] }}
                                        @endif
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        @if(count($linha) === 1)
                            <td style="width: 50%"></td>
                        @endif
                    </tr>
                </table>
            @endforeach
        </section>
    </div>
@endforeach
