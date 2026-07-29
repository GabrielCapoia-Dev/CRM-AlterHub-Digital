<footer class="document-footer">
    <table class="footer-table">
        <tr>
            <td class="footer-company">
                {{ $empresa['razao_social'] }} - CNPJ {{ $empresa['cnpj'] }}
            </td>
            <td class="footer-generated">
                Gerado em {{ $documento['gerado_em'] }}
            </td>
        </tr>
    </table>
</footer>
