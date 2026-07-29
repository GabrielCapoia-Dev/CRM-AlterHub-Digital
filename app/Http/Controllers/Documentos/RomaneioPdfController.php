<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\Romaneio;
use App\Services\Documentos\DocumentoPdfGerado;
use App\Services\Documentos\RomaneioPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class RomaneioPdfController extends Controller
{
    public function __invoke(
        Request $request,
        Romaneio $romaneio,
        RomaneioPdfService $service,
    ): Response {
        Gate::authorize('print', $romaneio);

        try {
            $documento = $service->gerar($romaneio, $request->user());
        } catch (ValidationException $exception) {
            return $this->validationResponse($exception);
        }

        return $this->response(
            $documento,
            $request->boolean('download'),
        );
    }

    protected function validationResponse(ValidationException $exception): Response
    {
        $mensagem = collect($exception->errors())
            ->flatten()
            ->filter()
            ->implode(' ');

        return response(
            $mensagem ?: 'Nao foi possivel gerar o PDF do romaneio.',
            422,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    protected function response(DocumentoPdfGerado $documento, bool $download): Response
    {
        $filename = $this->filenameSeguro($documento->filename);
        $disposition = HeaderUtils::makeDisposition(
            $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
            Str::ascii($filename),
        );

        return response($documento->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($documento->bytes),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function filenameSeguro(string $filename): string
    {
        $filename = basename(str_replace(["\r", "\n", "\0"], '', $filename));

        return $filename !== '' ? $filename : 'romaneio.pdf';
    }
}
