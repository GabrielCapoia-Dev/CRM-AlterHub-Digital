<?php

namespace App\Services\Documentos;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\VendaOperacao;
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
    ): VendaPedidoFoto {
        Gate::forUser($actor)->authorize('addPhotos', $pedido);
        $this->validarArquivo($arquivo);
        $pathArmazenado = null;

        try {
            return DB::transaction(function () use (
                $pedido,
                $arquivo,
                $actor,
                $item,
                $descricao,
                &$pathArmazenado,
            ): VendaPedidoFoto {
                $pedido = VendaOperacaoPedido::query()
                    ->lockForUpdate()
                    ->findOrFail($pedido->id);
                $this->assertAceitaFotos($pedido);

                if ($item) {
                    $item = VendaOperacao::query()
                        ->lockForUpdate()
                        ->findOrFail($item->id);

                    if ((int) $item->venda_operacao_pedido_id !== (int) $pedido->id) {
                        throw ValidationException::withMessages([
                            'venda_operacao_id' => 'O item informado nao pertence ao pedido.',
                        ]);
                    }
                }

                $mime = (string) $arquivo->getMimeType();
                $extensao = self::EXTENSOES_POR_MIME[$mime];
                $disk = 'local';
                $diretorio = "pedidos/{$pedido->id}/fotos";
                $nome = Str::uuid()->toString().".{$extensao}";
                $pathArmazenado = $arquivo->storeAs($diretorio, $nome, $disk);

                if (! is_string($pathArmazenado) || $pathArmazenado === '') {
                    throw ValidationException::withMessages([
                        'arquivo' => 'Nao foi possivel armazenar a foto.',
                    ]);
                }

                $foto = VendaPedidoFoto::query()->create([
                    'venda_operacao_pedido_id' => $pedido->id,
                    'venda_operacao_id' => $item?->id,
                    'user_id' => $actor->id,
                    'disk' => $disk,
                    'path' => $pathArmazenado,
                    'nome_original' => $arquivo->getClientOriginalName(),
                    'mime_type' => $mime,
                    'tamanho_bytes' => (int) $arquivo->getSize(),
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
                        'nome_original' => $foto->nome_original,
                        'mime_type' => $foto->mime_type,
                        'tamanho_bytes' => $foto->tamanho_bytes,
                    ],
                );

                return $foto->fresh(['pedido', 'item', 'user']);
            });
        } catch (Throwable $exception) {
            if (is_string($pathArmazenado) && $pathArmazenado !== '') {
                Storage::disk('local')->delete($pathArmazenado);
            }

            throw $exception;
        }
    }

    public function armazenar(
        VendaOperacaoPedido $pedido,
        UploadedFile $arquivo,
        User $actor,
        ?VendaOperacao $item = null,
        ?string $descricao = null,
    ): VendaPedidoFoto {
        return $this->adicionar($pedido, $arquivo, $actor, $item, $descricao);
    }

    public function registrarArquivoArmazenado(
        VendaOperacaoPedido $pedido,
        string $path,
        string $nomeOriginal,
        User $actor,
        ?VendaOperacao $item = null,
        ?string $descricao = null,
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
                    'nome_original' => $foto->nome_original,
                    'mime_type' => $foto->mime_type,
                    'tamanho_bytes' => $foto->tamanho_bytes,
                ],
            );

            return $foto->fresh(['pedido', 'item', 'user']);
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
        $mime = (string) $arquivo->getMimeType();
        $tamanho = (int) $arquivo->getSize();

        if (! $arquivo->isValid()) {
            throw ValidationException::withMessages([
                'arquivo' => 'O envio da foto falhou. Tente novamente.',
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
