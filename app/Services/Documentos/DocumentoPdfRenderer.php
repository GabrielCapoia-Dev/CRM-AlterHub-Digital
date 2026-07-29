<?php

namespace App\Services\Documentos;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use RuntimeException;

class DocumentoPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $title): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isFontSubsettingEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ]);

        $pdf->addInfo([
            'Title' => $title,
            'Author' => (string) ($data['empresa']['razao_social'] ?? config('app.name')),
            'Creator' => config('app.name'),
            'Subject' => 'Documento comercial',
        ]);

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $logo = $this->materializarLogo((string) ($data['empresa']['logo_data_uri'] ?? ''));

        try {
            $this->registrarCabecalho(
                $canvas,
                (array) ($data['empresa'] ?? []),
                (array) ($data['documento'] ?? []),
                $logo,
            );

            return $pdf->output(['compress' => 1]);
        } finally {
            @unlink($logo['path']);
        }
    }

    /**
     * @param  array<string, mixed>  $empresa
     * @param  array<string, mixed>  $documento
     * @param  array{path: string, width: int, height: int}  $logo
     */
    protected function registrarCabecalho(
        Canvas $canvas,
        array $empresa,
        array $documento,
        array $logo,
    ): void {
        $canvas->page_script(function (
            int $pageNumber,
            int $pageCount,
            Canvas $page,
            FontMetrics $fonts,
        ) use ($empresa, $documento, $logo): void {
            $normal = $fonts->getFont('DejaVu Sans', 'normal');
            $bold = $fonts->getFont('DejaVu Sans', 'bold');
            $dark = [0.09, 0.09, 0.09];
            $muted = [0.34, 0.34, 0.34];
            $blue = [0.11, 0.29, 0.57];
            $rule = [0.12, 0.16, 0.22];

            $left = 26.0;
            $right = $page->get_width() - 26.0;
            $logoBoxWidth = 122.0;
            $logoBoxHeight = 46.0;
            $companyX = $left + 142.0;
            $documentWidth = 185.0;
            $documentLeft = $right - $documentWidth;
            $companyWidth = $documentLeft - $companyX - 14.0;

            $logoScale = min(
                $logoBoxWidth / $logo['width'],
                $logoBoxHeight / $logo['height'],
            );
            $logoWidth = $logo['width'] * $logoScale;
            $logoHeight = $logo['height'] * $logoScale;
            $logoX = $left + (($logoBoxWidth - $logoWidth) / 2);
            $logoY = 29.0 + (($logoBoxHeight - $logoHeight) / 2);
            $page->image($logo['path'], $logoX, $logoY, $logoWidth, $logoHeight, 'normal');

            $nomeComercial = $this->texto((string) ($empresa['nome_comercial'] ?? ''));
            $razaoSocial = $this->texto((string) ($empresa['razao_social'] ?? ''));
            $nomePrincipal = $nomeComercial !== '' ? $nomeComercial : $razaoSocial;
            $nomePrincipal = $this->limitar($nomePrincipal, $fonts, $bold, 12.0, $companyWidth);
            $page->text($companyX, 27.0, $nomePrincipal, $bold, 12.0, $blue);

            $companyY = 45.0;

            if ($nomeComercial !== '' && $razaoSocial !== '') {
                $legal = $this->limitar($razaoSocial, $fonts, $normal, 7.3, $companyWidth);
                $page->text($companyX, $companyY, $legal, $normal, 7.3, $muted);
                $companyY += 11.5;
            }

            $cnpj = $this->texto((string) ($empresa['cnpj'] ?? ''));

            if ($cnpj !== '') {
                $cnpj = $this->limitar('CNPJ '.$cnpj, $fonts, $normal, 7.3, $companyWidth);
                $page->text($companyX, $companyY, $cnpj, $normal, 7.3, $muted);
                $companyY += 11.5;
            }

            $complemento = $this->texto((string) ($empresa['texto_complementar'] ?? ''));

            if ($complemento !== '') {
                $complemento = $this->limitar($complemento, $fonts, $normal, 7.0, $companyWidth);
                $page->text($companyX, $companyY, $complemento, $normal, 7.0, $muted);
            }

            $titulo = $this->limitar(
                $this->texto((string) ($documento['titulo'] ?? '')),
                $fonts,
                $bold,
                13.0,
                $documentWidth,
            );
            $this->textoDireita($page, $fonts, $titulo, $bold, 13.0, $right, 27.0, $dark);

            $numero = $this->limitar(
                $this->texto((string) ($documento['numero'] ?? '')),
                $fonts,
                $bold,
                9.2,
                $documentWidth,
            );
            $this->textoDireita($page, $fonts, $numero, $bold, 9.2, $right, 46.0, $dark);

            $data = $this->limitar(
                $this->texto((string) ($documento['data'] ?? 'Data não informada')),
                $fonts,
                $normal,
                7.8,
                $documentWidth,
            );
            $this->textoDireita($page, $fonts, $data, $normal, 7.8, $right, 60.0, $muted);

            $status = mb_strtoupper($this->texto((string) ($documento['status'] ?? '')));

            if ($status !== '') {
                $status = $this->limitar($status, $fonts, $bold, 6.7, $documentWidth - 10.0);
                $statusWidth = $fonts->getTextWidth($status, $bold, 6.7) + 10.0;
                $statusX = $right - $statusWidth;
                $page->rectangle($statusX, 73.0, $statusWidth, 13.0, $rule, 0.7);
                $page->text($statusX + 5.0, 75.0, $status, $bold, 6.7, $rule);
            }

            $page->line($left, 92.0, $right, 92.0, $rule, 1.5);

            $pagina = "Página {$pageNumber} de {$pageCount}";
            $this->textoDireita(
                $page,
                $fonts,
                $pagina,
                $normal,
                8.0,
                $right,
                $page->get_height() - 25.0,
                $muted,
            );
        });
    }

    /**
     * @return array{path: string, width: int, height: int}
     */
    protected function materializarLogo(string $dataUri): array
    {
        if (! preg_match('/\Adata:(image\/(?:jpeg|png|webp));base64,(.+)\z/s', $dataUri, $matches)) {
            throw new RuntimeException('O logotipo institucional não possui um data URI válido.');
        }

        $base64 = preg_replace('/\s+/u', '', $matches[2]);
        $bytes = is_string($base64) ? base64_decode($base64, true) : false;
        $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;

        if (! is_string($bytes) || $bytes === '' || ! is_array($info)) {
            throw new RuntimeException('Não foi possível preparar o logotipo institucional para o PDF.');
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        if ($width < 1 || $height < 1) {
            throw new RuntimeException('O logotipo institucional possui dimensões inválidas.');
        }

        $path = tempnam(sys_get_temp_dir(), 'crm-doc-logo-');

        if ($path === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário do logotipo.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new RuntimeException('Não foi possível gravar o logotipo temporário.');
            }
        } catch (\Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    /**
     * @param  array<int, float>  $color
     */
    protected function textoDireita(
        Canvas $canvas,
        FontMetrics $fonts,
        string $text,
        string $font,
        float $size,
        float $right,
        float $y,
        array $color,
    ): void {
        $width = $fonts->getTextWidth($text, $font, $size);
        $canvas->text($right - $width, $y, $text, $font, $size, $color);
    }

    protected function limitar(
        string $text,
        FontMetrics $fonts,
        string $font,
        float $size,
        float $maxWidth,
    ): string {
        if ($fonts->getTextWidth($text, $font, $size) <= $maxWidth) {
            return $text;
        }

        $suffix = '...';
        $suffixWidth = $fonts->getTextWidth($suffix, $font, $size);

        if ($suffixWidth >= $maxWidth) {
            return '';
        }

        $low = 0;
        $high = mb_strlen($text);

        while ($low < $high) {
            $middle = (int) ceil(($low + $high) / 2);
            $candidate = rtrim(mb_substr($text, 0, $middle)).$suffix;

            if ($fonts->getTextWidth($candidate, $font, $size) <= $maxWidth) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return rtrim(mb_substr($text, 0, $low)).$suffix;
    }

    protected function texto(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
