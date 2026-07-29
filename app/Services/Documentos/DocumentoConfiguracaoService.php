<?php

namespace App\Services\Documentos;

use App\Models\DocumentoConfiguracao;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentoConfiguracaoService
{
    public function __construct(
        protected DocumentoImagemService $imagens,
    ) {}

    public function obterValida(): DocumentoConfiguracao
    {
        $configuracao = DocumentoConfiguracao::query()
            ->where('chave', 'padrao')
            ->first();

        if (! $configuracao) {
            throw ValidationException::withMessages([
                'configuracao_documentos' => 'Configure os dados institucionais antes de gerar documentos.',
            ]);
        }

        $erros = [];

        if (blank($configuracao->razao_social)) {
            $erros['razao_social'] = 'Informe a razão social usada nos documentos.';
        }

        if (blank($configuracao->cnpj)) {
            $erros['cnpj'] = 'Informe o CNPJ usado nos documentos.';
        } elseif (! TaxIdentifier::isValid($configuracao->cnpj)) {
            $erros['cnpj'] = 'Informe um CNPJ válido para gerar documentos.';
        }

        $disk = (string) ($configuracao->logo_disk ?: 'local');
        $path = trim((string) $configuracao->logo_path);

        if ($disk !== 'local') {
            $erros['logo_path'] = 'O logotipo institucional deve estar no storage privado local.';
        } elseif ($path === '' || ! Storage::disk('local')->exists($path)) {
            $erros['logo_path'] = 'Envie um logotipo institucional válido antes de gerar documentos.';
        } else {
            try {
                $this->imagens->dataUriOriginal('local', $path);
            } catch (Throwable) {
                $erros['logo_path'] = 'O arquivo configurado como logotipo não é uma imagem válida.';
            }
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        return $configuracao;
    }

    /**
     * @return array{
     *   razao_social: string,
     *   cnpj: string,
     *   nome_comercial: string|null,
     *   texto_complementar: string|null,
     *   logo_data_uri: string,
     *   exibir_valores_romaneio: bool
     * }
     */
    public function dadosParaDocumento(?DocumentoConfiguracao $configuracao = null): array
    {
        $configuracao ??= $this->obterValida();

        return [
            'razao_social' => trim((string) $configuracao->razao_social),
            'cnpj' => (string) TaxIdentifier::formatForDisplay($configuracao->cnpj),
            'nome_comercial' => $this->nullableString($configuracao->nome_comercial),
            'texto_complementar' => $this->nullableString($configuracao->texto_complementar),
            'logo_data_uri' => $this->imagens->dataUriOriginal(
                (string) ($configuracao->logo_disk ?: 'local'),
                (string) $configuracao->logo_path,
            ),
            'exibir_valores_romaneio' => (bool) $configuracao->exibir_valores_romaneio,
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
