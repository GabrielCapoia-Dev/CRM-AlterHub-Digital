<?php

namespace App\Services\Documentos;

use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentoImagemService
{
    /** @var list<string> */
    protected const MIMES_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    public function dataUriOriginal(string $disk, string $path): string
    {
        [$bytes, $mime] = $this->lerImagem($disk, $path);

        return sprintf('data:%s;base64,%s', $mime, base64_encode($bytes));
    }

    /**
     * @return array{bytes: string, mime_type: string, filename: string, size: int}
     */
    public function arquivoOriginal(object $foto): array
    {
        $disk = (string) ($this->atributo($foto, 'disk') ?: 'local');
        $path = trim((string) $this->atributo($foto, 'path'));

        if ($path === '') {
            throw new RuntimeException('A foto não possui um caminho de armazenamento válido.');
        }

        [$bytes, $mime] = $this->lerImagem($disk, $path);
        $filename = trim((string) $this->atributo($foto, 'nome_original'));

        return [
            'bytes' => $bytes,
            'mime_type' => $mime,
            'filename' => $filename !== '' ? $filename : basename($path),
            'size' => strlen($bytes),
        ];
    }

    public function fotoParaPdf(
        object $foto,
        int $maxWidth = 1400,
        int $maxHeight = 1400,
        int $quality = 78,
    ): string {
        $arquivo = $this->arquivoOriginal($foto);
        $source = @imagecreatefromstring($arquivo['bytes']);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Não foi possível processar a foto para o PDF.');
        }

        try {
            $orientation = $arquivo['mime_type'] === 'image/jpeg'
                ? $this->orientacaoExif($arquivo['bytes'])
                : 1;

            $source = $this->aplicarOrientacao($source, $orientation);
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, $maxWidth / $width, $maxHeight / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $target instanceof GdImage) {
                throw new RuntimeException('Não foi possível redimensionar a foto para o PDF.');
            }

            try {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
                imagecopyresampled(
                    $target,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $width,
                    $height,
                );

                ob_start();

                if (! imagejpeg($target, null, max(45, min(90, $quality)))) {
                    ob_end_clean();

                    throw new RuntimeException('Não foi possível comprimir a foto para o PDF.');
                }

                $bytes = ob_get_clean();
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Não foi possível obter a versão da foto para o PDF.');
        }

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function lerImagem(string $disk, string $path): array
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            throw new RuntimeException('Arquivo de imagem não encontrado no storage.');
        }

        $bytes = $filesystem->get($path);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('O arquivo de imagem está vazio.');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;

        if (! is_string($mime) || ! in_array($mime, self::MIMES_PERMITIDOS, true)) {
            throw new RuntimeException('Formato de imagem não permitido.');
        }

        return [$bytes, $mime];
    }

    protected function atributo(object $model, string $name): mixed
    {
        if (method_exists($model, 'getAttribute')) {
            return $model->getAttribute($name);
        }

        return $model->{$name} ?? null;
    }

    protected function orientacaoExif(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $temp = tempnam(sys_get_temp_dir(), 'crm-doc-img-');

        if ($temp === false) {
            return 1;
        }

        try {
            if (file_put_contents($temp, $bytes) === false) {
                return 1;
            }

            $exif = @exif_read_data($temp, 'IFD0', true, false);
            $orientation = is_array($exif)
                ? ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1)
                : 1;

            return max(1, min(8, (int) $orientation));
        } finally {
            @unlink($temp);
        }
    }

    protected function aplicarOrientacao(GdImage $image, int $orientation): GdImage
    {
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 3) {
            $image = $this->rotacionar($image, 180);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        } elseif ($orientation === 5) {
            $image = $this->rotacionar($image, -90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 6) {
            $image = $this->rotacionar($image, -90);
        } elseif ($orientation === 7) {
            $image = $this->rotacionar($image, 90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 8) {
            $image = $this->rotacionar($image, 90);
        }

        return $image;
    }

    protected function rotacionar(GdImage $image, int $degrees): GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);

        if (! $rotated instanceof GdImage) {
            throw new RuntimeException('Não foi possível corrigir a orientação da foto.');
        }

        imagedestroy($image);

        return $rotated;
    }
}
