<?php

namespace App\Services\Documentos;

use App\Enum\SeparacaoStatus;
use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\RomaneioPedido;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaWorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendaSeparacaoService
{
    public function __construct(
        protected VendaWorkflowService $workflowService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lotes
     */
    public function registrarLotes(
        VendaOperacao $item,
        array $lotes,
        User $actor,
    ): VendaOperacao {
        $pedido = $item->vendaOperacaoPedido()->firstOrFail();
        Gate::forUser($actor)->authorize('manageLots', $pedido);

        return DB::transaction(function () use ($item, $lotes, $actor): VendaOperacao {
            $pedidoId = VendaOperacao::query()
                ->whereKey($item->id)
                ->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedidoId);
            $item = VendaOperacao::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $this->assertPedidoSeparavel($pedido);
            $this->assertSemRomaneioAtivo($pedido);

            $normalizados = $this->normalizarLotes($lotes, (float) $item->quantidade);
            $existentes = VendaOperacaoLote::withTrashed()
                ->where('venda_operacao_id', $item->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $antes = $existentes
                ->whereNull('deleted_at')
                ->map(fn (VendaOperacaoLote $lote): array => $this->snapshotLote($lote))
                ->values()
                ->all();
            $porNumero = $existentes->keyBy(
                fn (VendaOperacaoLote $lote): string => mb_strtolower($lote->numero_lote),
            );
            $numerosMantidos = [];

            foreach ($normalizados as $dados) {
                $chave = mb_strtolower($dados['numero_lote']);
                $numerosMantidos[] = $chave;
                $lote = $porNumero->get($chave);

                if ($lote) {
                    $lote->forceFill([
                        ...$dados,
                        'user_id' => $actor->id,
                        'deleted_by' => null,
                        'deleted_at' => null,
                    ])->save();

                    continue;
                }

                VendaOperacaoLote::query()->create([
                    ...$dados,
                    'venda_operacao_id' => $item->id,
                    'user_id' => $actor->id,
                ]);
            }

            foreach ($existentes as $lote) {
                if ($lote->trashed()
                    || in_array(mb_strtolower($lote->numero_lote), $numerosMantidos, true)) {
                    continue;
                }

                $lote->forceFill(['deleted_by' => $actor->id])->save();
                $lote->delete();
            }

            $depois = VendaOperacaoLote::query()
                ->where('venda_operacao_id', $item->id)
                ->orderBy('id')
                ->get()
                ->map(fn (VendaOperacaoLote $lote): array => $this->snapshotLote($lote))
                ->all();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'lotes_separacao_atualizados',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'venda_operacao_id' => $item->id,
                    'antes' => $antes,
                    'depois' => $depois,
                ],
            );

            return $item->fresh(['lotes', 'produto']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lotes
     */
    public function salvarLotes(
        VendaOperacao $item,
        array $lotes,
        User $actor,
    ): VendaOperacao {
        return $this->registrarLotes($item, $lotes, $actor);
    }

    public function atualizarObservacaoPedido(
        VendaOperacaoPedido $pedido,
        ?string $observacao,
        User $actor,
    ): VendaOperacaoPedido {
        Gate::forUser($actor)->authorize('manageLots', $pedido);
        $observacao = $this->nullableString($observacao);

        if ($observacao !== null && mb_strlen($observacao) > 2000) {
            throw ValidationException::withMessages([
                'observacao' => 'A observacao deve possuir no maximo 2.000 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $observacao, $actor): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            $this->assertPedidoSeparavel($pedido);
            $this->assertSemRomaneioAtivo($pedido);
            $anterior = $this->nullableString($pedido->separacao_observacao);

            if ($anterior === $observacao) {
                return $pedido->fresh(['historicos']);
            }

            $pedido->forceFill([
                'separacao_observacao' => $observacao,
                'observacao' => $observacao,
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'observacao_separacao_atualizada',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'observacao_anterior' => $anterior,
                    'observacao' => $observacao,
                ],
            );

            return $pedido->fresh(['historicos']);
        });
    }

    public function informarVolumes(
        VendaOperacaoPedido $pedido,
        int $quantidadeVolumes,
        User $actor,
    ): VendaOperacaoPedido {
        Gate::forUser($actor)->authorize('manageLots', $pedido);

        return DB::transaction(function () use ($pedido, $quantidadeVolumes, $actor): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            $this->assertPedidoSeparavel($pedido);
            $this->assertSemRomaneioAtivo($pedido);

            if ($quantidadeVolumes <= 0) {
                throw ValidationException::withMessages([
                    'quantidade_volumes' => 'Informe ao menos um volume para o pedido.',
                ]);
            }

            $anterior = $pedido->quantidade_volumes;
            $itens = VendaOperacao::query()
                ->with('produto')
                ->where('venda_operacao_pedido_id', $pedido->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($itens as $item) {
                if ((float) $item->peso_unitario_kg_snapshot > 0) {
                    continue;
                }

                $pesoProduto = (float) ($item->produto?->peso_unitario_kg ?? 0);

                if ($pesoProduto > 0) {
                    $item->forceFill([
                        'peso_unitario_kg_snapshot' => round($pesoProduto, 4),
                    ])->save();
                }
            }

            $pedido->forceFill([
                'quantidade_volumes' => $quantidadeVolumes,
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'volumes_separacao_atualizados',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'quantidade_volumes_anterior' => $anterior,
                    'quantidade_volumes' => $quantidadeVolumes,
                ],
            );

            return $pedido->fresh(['vendasOperacao.produto', 'historicos']);
        });
    }

    public function definirVolumes(
        VendaOperacaoPedido $pedido,
        int $quantidadeVolumes,
        User $actor,
    ): VendaOperacaoPedido {
        return $this->informarVolumes($pedido, $quantidadeVolumes, $actor);
    }

    public function concluirSeparacao(
        VendaOperacaoPedido $pedido,
        User $actor,
    ): VendaOperacaoPedido {
        Gate::forUser($actor)->authorize('manageLots', $pedido);

        return DB::transaction(function () use ($pedido, $actor): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);
            $this->assertPedidoSeparavel($pedido);
            $this->assertSemRomaneioAtivo($pedido);

            $itens = VendaOperacao::query()
                ->with(['produto', 'lotes.fotos'])
                ->where('venda_operacao_pedido_id', $pedido->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($itens->isEmpty()) {
                throw ValidationException::withMessages([
                    'itens' => 'O pedido não possui produtos para separar.',
                ]);
            }

            $pendencias = [];

            foreach ($itens as $item) {
                $produto = $item->produto_nome_snapshot ?: 'Item #'.$item->id;
                $quantidadeConfirmada = round((float) $item->quantidade, 4);
                $quantidadeLotes = round((float) $item->lotes->sum('quantidade'), 4);

                if (abs($quantidadeConfirmada - $quantidadeLotes) > 0.0001) {
                    $pendencias[] = sprintf(
                        '%s: os lotes somam %.4f, mas a quantidade confirmada é %.4f',
                        $produto,
                        $quantidadeLotes,
                        $quantidadeConfirmada,
                    );
                }

                foreach ($item->lotes as $lote) {
                    if ($lote->fotos->isEmpty()) {
                        $pendencias[] = sprintf(
                            '%s, lote %s: adicione ao menos uma foto',
                            $produto,
                            $lote->numero_lote,
                        );
                    }
                }

                $pesoAnterior = $item->peso_unitario_kg_snapshot;
                $pesoSnapshot = (float) $pesoAnterior;

                if ($pesoSnapshot <= 0) {
                    $pesoProduto = (float) ($item->produto?->peso_unitario_kg ?? 0);

                    if (is_finite($pesoProduto) && $pesoProduto > 0) {
                        $pesoSnapshot = round($pesoProduto, 4);
                        $item->forceFill([
                            'peso_unitario_kg_snapshot' => $pesoSnapshot,
                        ])->save();

                        $this->workflowService->registrarHistorico(
                            $pedido,
                            $actor,
                            'peso_separacao_atualizado',
                            $pedido->status,
                            $pedido->status,
                            metadados: [
                                'venda_operacao_id' => $item->id,
                                'peso_anterior' => $pesoAnterior,
                                'peso_unitario_kg_snapshot' => $pesoSnapshot,
                                'origem' => 'cadastro_produto',
                            ],
                        );
                    }
                }

                if ($pesoSnapshot <= 0) {
                    $pendencias[] = $produto.': cadastre o peso unitário do produto';
                }
            }

            if ($pendencias !== []) {
                throw ValidationException::withMessages([
                    'separacao' => 'Conclua a separação: '.implode('; ', $pendencias).'.',
                ]);
            }

            $statusAnterior = $pedido->separacao_status;
            $pedido->forceFill([
                'separacao_status' => SeparacaoStatus::Separado->value,
                'separado_por' => $actor->id,
                'separado_em' => now(),
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'separacao_concluida',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'separacao_status_anterior' => $statusAnterior,
                    'separacao_status' => SeparacaoStatus::Separado->value,
                    'total_itens' => $itens->count(),
                ],
            );

            return $pedido->fresh([
                'vendasOperacao.lotes',
                'vendasOperacao.fotos',
                'separadoPor',
                'historicos',
            ]);
        });
    }

    public function atualizarPesoSnapshot(
        VendaOperacao $item,
        User $actor,
    ): VendaOperacao {
        $pedido = $item->vendaOperacaoPedido()->firstOrFail();
        Gate::forUser($actor)->authorize('manageLots', $pedido);

        return DB::transaction(function () use ($item, $actor): VendaOperacao {
            $pedidoId = VendaOperacao::query()
                ->whereKey($item->id)
                ->value('venda_operacao_pedido_id');
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedidoId);
            $item = VendaOperacao::query()
                ->with('produto')
                ->lockForUpdate()
                ->findOrFail($item->id);

            $this->assertPedidoSeparavel($pedido);
            $this->assertSemRomaneioAtivo($pedido);

            $peso = (float) ($item->produto?->peso_unitario_kg ?? 0);

            if (! is_finite($peso) || $peso <= 0) {
                throw ValidationException::withMessages([
                    'peso_unitario_kg' => 'Cadastre um peso unitario maior que zero no produto.',
                ]);
            }

            $anterior = $item->peso_unitario_kg_snapshot;
            $item->forceFill([
                'peso_unitario_kg_snapshot' => round($peso, 4),
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $actor,
                'peso_separacao_atualizado',
                $pedido->status,
                $pedido->status,
                metadados: [
                    'venda_operacao_id' => $item->id,
                    'peso_anterior' => $anterior,
                    'peso_unitario_kg_snapshot' => round($peso, 4),
                ],
            );

            return $item->fresh(['produto', 'lotes']);
        });
    }

    protected function assertPedidoSeparavel(VendaOperacaoPedido $pedido): void
    {
        if ($pedido->status !== VendaStatus::Confirmada->value) {
            throw ValidationException::withMessages([
                'status' => 'Somente pedidos confirmados podem receber dados de separacao.',
            ]);
        }
    }

    protected function assertSemRomaneioAtivo(VendaOperacaoPedido $pedido): void
    {
        if (RomaneioPedido::query()
            ->where('pedido_ativo_id', $pedido->id)
            ->lockForUpdate()
            ->exists()) {
            throw ValidationException::withMessages([
                'romaneio' => 'O pedido ja pertence a um romaneio ativo.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lotes
     * @return list<array<string, mixed>>
     */
    protected function normalizarLotes(array $lotes, float $quantidadeItem): array
    {
        $normalizados = [];
        $vistos = [];
        $soma = 0.0;

        foreach (array_values($lotes) as $indice => $lote) {
            $numero = trim((string) ($lote['numero_lote'] ?? ''));

            if ($numero === '' || mb_strlen($numero) > 100) {
                throw ValidationException::withMessages([
                    "lotes.{$indice}.numero_lote" => 'Informe um lote com ate 100 caracteres.',
                ]);
            }

            $chave = mb_strtolower($numero);

            if (isset($vistos[$chave])) {
                throw ValidationException::withMessages([
                    "lotes.{$indice}.numero_lote" => 'O mesmo lote foi informado mais de uma vez.',
                ]);
            }

            $quantidade = round((float) ($lote['quantidade'] ?? 0), 4);

            if (! is_finite($quantidade) || $quantidade <= 0) {
                throw ValidationException::withMessages([
                    "lotes.{$indice}.quantidade" => 'Informe uma quantidade de lote maior que zero.',
                ]);
            }

            $ano = filled($lote['ano_fabricacao'] ?? null)
                ? (int) $lote['ano_fabricacao']
                : null;

            if ($ano !== null && ($ano < 1900 || $ano > (int) now()->format('Y') + 1)) {
                throw ValidationException::withMessages([
                    "lotes.{$indice}.ano_fabricacao" => 'Informe um ano de fabricacao valido.',
                ]);
            }

            $fabricacao = $this->normalizarData(
                $lote['data_fabricacao'] ?? null,
                "lotes.{$indice}.data_fabricacao",
            );
            $validade = $this->normalizarData(
                $lote['data_validade'] ?? null,
                "lotes.{$indice}.data_validade",
            );

            if ($fabricacao && $validade && $validade < $fabricacao) {
                throw ValidationException::withMessages([
                    "lotes.{$indice}.data_validade" => 'A validade nao pode ser anterior a fabricacao.',
                ]);
            }

            $vistos[$chave] = true;
            $soma = round($soma + $quantidade, 4);
            $normalizados[] = [
                'numero_lote' => $numero,
                'quantidade' => $quantidade,
                'ano_fabricacao' => $ano,
                'data_fabricacao' => $fabricacao,
                'data_validade' => $validade,
                'observacao' => $this->nullableString($lote['observacao'] ?? null),
            ];
        }

        if ($soma > round($quantidadeItem, 4)) {
            throw ValidationException::withMessages([
                'lotes' => 'A soma dos lotes nao pode ultrapassar a quantidade confirmada do item.',
            ]);
        }

        return $normalizados;
    }

    protected function normalizarData(mixed $valor, string $campo): ?string
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Carbon::parse((string) $valor)->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                $campo => 'Informe uma data valida.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotLote(VendaOperacaoLote $lote): array
    {
        return [
            'id' => $lote->id,
            'numero_lote' => $lote->numero_lote,
            'quantidade' => (float) $lote->quantidade,
            'ano_fabricacao' => $lote->ano_fabricacao,
            'data_fabricacao' => $lote->data_fabricacao?->toDateString(),
            'data_validade' => $lote->data_validade?->toDateString(),
            'observacao' => $lote->observacao,
        ];
    }

    protected function nullableString(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
