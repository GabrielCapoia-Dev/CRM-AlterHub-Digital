<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use App\Models\VendaOperacaoPedido;
use App\Models\VendaPedidoFoto;
use App\Services\Documentos\DocumentoImagemService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class VendaPedidoFotoController extends Controller
{
    public function visualizar(
        VendaOperacaoPedido $pedido,
        VendaPedidoFoto $foto,
        DocumentoImagemService $imagens,
    ): Response {
        return $this->responder($pedido, $foto, $imagens, false);
    }

    public function baixar(
        VendaOperacaoPedido $pedido,
        VendaPedidoFoto $foto,
        DocumentoImagemService $imagens,
    ): Response {
        return $this->responder($pedido, $foto, $imagens, true);
    }

    protected function responder(
        VendaOperacaoPedido $pedido,
        VendaPedidoFoto $foto,
        DocumentoImagemService $imagens,
        bool $download,
    ): Response {
        abort_unless(
            (int) $foto->venda_operacao_pedido_id === (int) $pedido->getKey(),
            404,
        );

        Gate::authorize('view', $foto);

        $arquivo = $imagens->arquivoOriginal($foto);
        $filename = $this->filenameSeguro($arquivo['filename'], $arquivo['mime_type']);
        $disposition = HeaderUtils::makeDisposition(
            $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
            Str::ascii($filename),
        );

        return response($arquivo['bytes'], 200, [
            'Content-Type' => $arquivo['mime_type'],
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) $arquivo['size'],
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    protected function filenameSeguro(string $filename, string $mime): string
    {
        $filename = basename(str_replace(["\r", "\n", "\0"], '', $filename));

        if ($filename !== '') {
            return $filename;
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return 'foto.'.$extension;
    }
}
