<?php

namespace App\Services\Documentos;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Models\VendaPedidoFoto;
use App\Services\Operacao\VendaWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendaFotoService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> */
    protected const EXTENSOES_POR_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        protected VendaWorkflowService $workflowService,
    ) {}

    public function adicionar(
        VendaOperacaoPedido $pedido,
        UploadedFile $arquivo,
        User $actor,
        ?VendaOperacao $item = null,
        ?string $descricao = null,
        ?VendaOperacaoLote $lote = null,
    ): VendaPedidoFoto {
        $armazenado = $this->armazenarUploadPendente($pedido, $arquivo, $actor);

        try {
            return $this->registrarArquivoArmazenado(
                $pedido,
                $armazenado['path'],
                $armazenado['nome_original'],
                $actor,
                $item,
                $descricao,
                $lote,
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($armazenado['path']);

            throw $exception;
        }
    }

    /**
     * Valida e transfere o upload antes de adquirir locks de banco. O chamador
     * deve remover o caminho caso a transação que registra a foto seja desfeita.
     *
     * @return array{path:string,nome_original:string}
     */
    public function armazenarUploadPendente(
        VendaOperacaoPedido $pedido,
        UploadedFile $arquivo,
        User $actor,
    ): array {
        Gate::forUser($actor)->authorize('addPhotos', $pedido);
        $this->validarArquivo($arquivo);

        try {
            $mime = (string) $arquivo->getMimeType();
            $extensao = self::EXTENSOES_POR_MIME[$mime];
            $diretorio = "pedidos/{$pedido->id}/fotos";
            $nome = Str::uuid()->toString().".{$extensao}";
            $path = $arquivo->storeAs($diretorio, $nome, 'local');
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'arquivo' => 'Não foi possível armazenar a foto. Envie o arquivo novamente.',
            ]);
        }

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'arquivo' => 'Não foi possível armazenar a foto. Envie o arquivo novamente.',
            ]);
        }

        return [
            'path' => $path,
            'nome_original' => $arquivo->getClientOriginalName(),
        ];
    }

    public function armazenar(
        VendaOperacaoPedido $pedido,
        UploadedFile $arquivo,
        User $actor,
        ?VendaOperacao $item = null,
        ?string $descricao = null,
        ?VendaOperacaoLote $lote = null,
    ): VendaPedidoFoto {
        return $this->adicionar($pedido, $arquivo, $actor, $item, $descricao, $lote);
    }

    public function registrarArquivoArmazenado(
        VendaOperacaoPedido $pedido,
        string $path,
        string $nomeOriginal,
        User $actor,
        ?VendaOperacao $item = null,
        ?string $descricao = null,
        ?VendaOperacaoLote $lote = null,
    ): VendaPedidoFoto {
        Gate::forUser($actor)->authorize('addPhotos', $pedido);
        $disk = 'local';
        $path = trim($path);
        $prefixoEsperado = "pedidos/{$pedido->id}/fotos/";

        if ($path === '' || ! str_starts_with($path, $prefixoEsperado)) {
            throw ValidationException::withMessages([
                'arquivo' => 'O arquivo armazenado não pertence a este pedido.',
            ]);
        }

        try {
            $mime = (string) Storage::disk($disk)->mimeType($path);
            $tamanho = (int) Storage::disk($disk)->size($path);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'arquivo' => 'A imagem não está mais disponível. Envie o arquivo novamente.',
            ]);
        }

        if (! array_key_exists($mime, self::EXTENSOES_POR_MIME)) {
            throw ValidationException::withMessages([
                'arquivo' => 'Envie uma imagem JPEG, PNG ou WebP.',
            ]);
        }

        if ($tamanho <= 0 || $tamanho > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'arquivo' => 'A imagem deve possuir no máximo 10 MB.',
            ]);
        }

        return DB::transaction(function () use (
            $pedido,
            $path,
            $nomeOriginal,
            $actor,
            $item,
            $descricao,
            $lote,
            $disk,
            $mime,
            $tamanho,
        ): VendaPedidoFoto {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);
            $this->assertAceitaFotos($pedido);

            if ($item) {
                $item = VendaOperacao::query()->lockForUpdate()->findOrFail($item->id);

                if ((int) $item->venda_operacao_pedido_id !== (int) $pedido->id) {
                    throw ValidationException::withMessages([
                        'venda_operacao_id' => 'O item informado não pertence ao pedido.',
                    ]);
                }
            }

            if ($lote) {
                $lote = VendaOperacaoLote::query()->lockForUpdate()->findOrFail($lote->id);

                if (! $item || (int) $lote->venda_operacao_id !== (int) $item->id) {
                    throw ValidationException::withMessages([
                        'venda_operacao_lote_id' => 'O lote informado não pertence ao item do pedido.',
                    ]);
                }
            }

            $existente = VendaPedidoFoto::query()
                ->where('venda_operacao_pedido_id', $pedido->id)
                ->where('path', $path)
                ->first();

            if ($existente) {
                return $existente;
            }

            $foto = VendaPedidoFoto::query()->create([
                'venda_operacao_pedido_id' => $pedido->id,
                'venda_operacao_id' => $item?->id,
                'venda_operacao_lote_id' => $lote?->id,
                'user_id' => $actor->id,
                'disk' => $disk,
                'path' => $path,
                'nome_original' => mb_substr(trim($nomeOriginal) ?: basename($path), 0, 255),
                'mime_type' => $mime,
                'tamanho_bytes' => $tamanho,
                'descricao' => $this->nullableString($descricao),
            ]);

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'foto_separacao_adicionada',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'foto_id' => $foto->id,
                    'venda_operacao_id' => $item?->id,
                    'venda_operacao_lote_id' => $lote?->id,
                    'nome_original' => $foto->nome_original,
                    'mime_type' => $foto->mime_type,
                    'tamanho_bytes' => $foto->tamanho_bytes,
                ],
            );

            return $foto->fresh(['pedido', 'item', 'lote', 'user']);
        });
    }

    public function remover(
        VendaPedidoFoto $foto,
        User $actor,
    ): VendaPedidoFoto {
        Gate::forUser($actor)->authorize('delete', $foto);

        $removida = DB::transaction(function () use ($foto, $actor): VendaPedidoFoto {
            $pedidoId = VendaPedidoFoto::withTrashed()
                ->whereKey($foto->id)
                ->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedidoId);
            $foto = VendaPedidoFoto::withTrashed()
                ->lockForUpdate()
                ->findOrFail($foto->id);

            if ($foto->trashed()) {
                return $foto;
            }

            $foto->forceFill([
                'removida_por' => $actor->id,
                'removida_em' => now(),
            ])->save();
            $foto->delete();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'foto_separacao_removida',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'foto_id' => $foto->id,
                    'venda_operacao_id' => $foto->venda_operacao_id,
                    'venda_operacao_lote_id' => $foto->venda_operacao_lote_id,
                    'nome_original' => $foto->nome_original,
                    'disk' => $foto->disk,
                    'path' => $foto->path,
                ],
            );

            DB::afterCommit(
                fn () => Storage::disk($foto->disk)->delete($foto->path),
            );

            return $foto;
        });

        return $removida->fresh(['pedido', 'item', 'user', 'removidaPor'])
            ?? $removida;
    }

    protected function validarArquivo(UploadedFile $arquivo): void
    {
        if (! $arquivo->isValid()) {
            throw ValidationException::withMessages([
                'arquivo' => 'O envio da foto falhou. Tente novamente.',
            ]);
        }

        try {
            $mime = (string) $arquivo->getMimeType();
            $tamanho = (int) $arquivo->getSize();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'arquivo' => 'A imagem não está mais disponível. Envie o arquivo novamente.',
            ]);
        }

        if (! array_key_exists($mime, self::EXTENSOES_POR_MIME)) {
            throw ValidationException::withMessages([
                'arquivo' => 'Envie uma imagem JPEG, PNG ou WebP.',
            ]);
        }

        if ($tamanho <= 0 || $tamanho > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'arquivo' => 'A imagem deve possuir no maximo 10 MB.',
            ]);
        }
    }

    protected function assertAceitaFotos(VendaOperacaoPedido $pedido): void
    {
        if (! in_array($pedido->status, [
            VendaStatus::Confirmada->value,
            VendaStatus::ParcialmenteDespachada->value,
            VendaStatus::Despachada->value,
            VendaStatus::Concluida->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Somente pedidos confirmados podem receber fotos da separacao.',
            ]);
        }
    }

    protected function nullableString(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
