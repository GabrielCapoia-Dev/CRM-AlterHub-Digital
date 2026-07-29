<div class="document-header">
    <table class="header-table">
        <tr>
            <td class="brand-cell">
                <img
                    class="brand-logo"
                    src="{{ $empresa['logo_data_uri'] }}"
                    alt="{{ $empresa['nome_comercial'] ?: $empresa['razao_social'] }}"
                >
            </td>
            <td class="company-cell">
                @if($empresa['nome_comercial'])
                    <div class="company-name">{{ $empresa['nome_comercial'] }}</div>
                @endif
                <div class="company-legal">{{ $empresa['razao_social'] }}</div>
                <div class="company-cnpj">CNPJ {{ $empresa['cnpj'] }}</div>
                @if($empresa['texto_complementar'])
                    <div class="company-extra">{{ $empresa['texto_complementar'] }}</div>
                @endif
            </td>
            <td class="document-cell">
                <div class="document-title">{{ $documento['titulo'] }}</div>
                <div class="document-number">{{ $documento['numero'] }}</div>
                <div class="document-meta">
                    {{ $documento['data'] ?: 'Data não informada' }}
                    @if($documento['status'])
                        <span class="status-badge">{{ $documento['status'] }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</div>
