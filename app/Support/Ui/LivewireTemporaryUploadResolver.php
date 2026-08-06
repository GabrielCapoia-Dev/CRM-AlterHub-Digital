<?php

namespace App\Support\Ui;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class LivewireTemporaryUploadResolver
{
    /**
     * @return list<UploadedFile>
     */
    public static function resolve(mixed $uploads): array
    {
        if (TemporaryUploadedFile::canUnserialize($uploads)) {
            $uploads = TemporaryUploadedFile::unserializeFromLivewireRequest($uploads);
        }

        $files = [];
        self::collectFiles($uploads, $files);

        return $files;
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private static function collectFiles(mixed $upload, array &$files): void
    {
        if ($upload === null || $upload === []) {
            return;
        }

        if ($upload instanceof UploadedFile) {
            if ($upload instanceof TemporaryUploadedFile && ! $upload->exists()) {
                throw ValidationException::withMessages([
                    'arquivos' => 'O arquivo temporário expirou. Envie a imagem novamente.',
                ]);
            }

            $files[] = $upload;

            return;
        }

        if (is_array($upload)) {
            foreach ($upload as $nestedUpload) {
                self::collectFiles($nestedUpload, $files);
            }

            return;
        }

        throw ValidationException::withMessages([
            'arquivos' => 'O arquivo temporário expirou. Envie a imagem novamente.',
        ]);
    }
}
