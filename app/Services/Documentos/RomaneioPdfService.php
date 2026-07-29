<?php

namespace App\Services\Documentos;

use App\Models\Acesso\User;
use App\Models\Romaneio;
use App\Models\RomaneioHistorico;
use App\Models\RomaneioItem;
use App\Models\RomaneioPedido;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RomaneioPdfService
{
    public function __construct(
        protected DocumentoConfiguracaoService $configuracoes,
        protected DocumentoImagemService $imagens,
        protected DocumentoPdfRenderer $renderer,
    ) {}

    public function gerar(Romaneio $romaneio, ?User $usuario = null): DocumentoPdfGerado
    {
        $romaneio->loadMissing([
            'user',
            'pedidos.itens',
        ]);

        if ($romaneio->pedidos->isEmpty() || $romaneio->itens()->doesntExist()) {
            throw ValidationException::withMessages([
                'romaneio' => 'O romaneio não possui pedidos e produtos para gerar o PDF.',
            ]);
        }

        $empresa = $this->empresaDoSnapshotOuAtual($romaneio);
        $dados = $this->mapearRomaneio($romaneio);
        $filename = $this->filename($romaneio);
        $bytes = $this->renderer->render(
            'documentos.romaneio',
            [
                'empresa' => $empresa,
                'documento' => [
                    'titulo' => 'Romaneio de Carga',
                    'numero' => $dados['numero'],
                    'data' => $dados['gerado_em'],
                    'status' => $dados['status'],
                    'gerado_em' => $dados['impresso_em'],
                ],
                'romaneio' => $dados,
            ],
            'Romaneio de Carga - '.$dados['numero'],
        );

        $this->auditar($romaneio, $usuario, $filename);

        return new DocumentoPdfGerado($bytes, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function empresaDoSnapshotOuAtual(Romaneio $romaneio): array
    {
        $snapshot = $romaneio->empresa_snapshot;

        if (is_array($snapshot)
            && filled($snapshot['razao_social'] ?? null)
            && filled($snapshot['cnpj'] ?? null)) {
            $logoDataUri = $snapshot['logo_data_uri'] ?? null;

            if (! is_string($logoDataUri) || ! str_starts_with($logoDataUri, 'data:image/')) {
                try {
                    $logoDataUri = $this->imagens->dataUriOriginal(
                        (string) ($snapshot['logo_disk'] ?? 'local'),
                        (string) ($snapshot['logo_path'] ?? ''),
                    );
                } catch (Throwable) {
                    $logoDataUri = null;
                }
            }

            if (is_string($logoDataUri) && $logoDataUri !== '') {
                return [
                    'razao_social' => trim((string) $snapshot['razao_social']),
                    'cnpj' => (string) TaxIdentifier::formatForDisplay($snapshot['cnpj']),
                    'nome_comercial' => $this->nullableString($snapshot['nome_comercial'] ?? null),
                    'texto_complementar' => $this->nullableString($snapshot['texto_complementar'] ?? null),
                    'logo_data_uri' => $logoDataUri,
                    'exibir_valores_romaneio' => (bool) $romaneio->exibir_valores_comerciais,
                ];
            }
        }

        return $this->configuracoes->dadosParaDocumento();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearRomaneio(Romaneio $romaneio): array
    {
        $pedidos = $romaneio->pedidos
            ->map(fn (RomaneioPedido $pedido): array => $this->mapearPedido($pedido))
            ->values();

        $grupos = $pedidos
            ->groupBy(fn (array $pedido): string => (string) (
                $pedido['cliente_id'] ?: mb_strtolower($pedido['cliente']['nome'])
            ))
            ->map(fn ($pedidosCliente): array => [
                'cliente' => $pedidosCliente->first()['cliente'],
                'pedidos' => $pedidosCliente->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'numero' => $romaneio->codigo ?: 'ROM-'.$romaneio->getKey(),
            'status' => $romaneio->status === Romaneio::STATUS_CANCELADO ? 'Cancelado' : 'Ativo',
            'gerado_em' => $this->formatarDataHora($romaneio->gerado_em),
            'impresso_em' => $this->formatarDataHora(now()),
            'usuario_responsavel' => $this->nullableString($romaneio->user?->name),
            'observacao' => $this->nullableString($romaneio->observacao),
            'exibir_valores' => (bool) $romaneio->exibir_valores_comerciais,
            'grupos' => $grupos,
            'totais' => [
                'pedidos' => (int) $romaneio->total_pedidos,
                'clientes' => (int) $romaneio->total_clientes,
                'itens' => (int) $romaneio->total_itens,
                'quantidade' => $this->quantidade($romaneio->quantidade_total),
                'volumes' => (int) $romaneio->quantidade_volumes_total,
                'peso' => $this->peso($romaneio->peso_total_kg),
                'valor' => $this->moeda($romaneio->valor_total),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearPedido(RomaneioPedido $pedido): array
    {
        return [
            'cliente_id' => $pedido->cliente_id_snapshot,
            'numero' => $pedido->pedido_codigo_snapshot ?: 'PED-'.$pedido->venda_operacao_pedido_id,
            'data' => $this->formatarData($pedido->pedido_data_snapshot),
            'cliente' => [
                'nome' => $this->nullableString($pedido->cliente_nome_snapshot)
                    ?? 'Cliente não identificado',
                'documento' => $this->nullableString($pedido->cliente_documento_snapshot),
                'telefone' => $this->nullableString($pedido->cliente_telefone_snapshot),
                'email' => $this->nullableString($pedido->cliente_email_snapshot),
            ],
            'vendedor' => $this->nullableString($pedido->vendedor_nome_snapshot),
            'pagamento' => $this->nullableString($pedido->condicao_pagamento_snapshot),
            'endereco_entrega' => $this->formatarEndereco($pedido->entrega_endereco_snapshot),
            'observacao' => $this->nullableString($pedido->observacao_snapshot),
            'condicoes_comerciais' => $this->nullableString($pedido->condicoes_comerciais_snapshot),
            'volumes' => (int) $pedido->quantidade_volumes,
            'peso' => $this->peso($pedido->peso_total_kg),
            'valor' => $this->moeda($pedido->valor_total),
            'itens' => $pedido->itens
                ->map(fn (RomaneioItem $item): array => $this->mapearItem($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearItem(RomaneioItem $item): array
    {
        $lotes = collect($item->lotes_snapshot ?? [])
            ->map(fn (mixed $lote): array => [
                'lote' => $this->nullableString(is_array($lote) ? ($lote['numero_lote'] ?? $lote['lote'] ?? null) : $lote),
                'validade' => $this->formatarData(is_array($lote) ? ($lote['data_validade'] ?? $lote['validade'] ?? null) : null),
            ])
            ->filter(fn (array $lote): bool => filled($lote['lote']) || filled($lote['validade']))
            ->values()
            ->all();

        return [
            'produto' => $item->produto_nome_snapshot,
            'quantidade' => $this->quantidade($item->quantidade),
            'unidade' => $this->nullableString($item->unidade_snapshot) ?? 'UN',
            'valor_unitario' => $this->moeda($item->preco_unitario),
            'subtotal' => $this->moeda($item->subtotal),
            'peso' => $this->peso($item->peso_total_kg),
            'lotes' => $lotes,
            'observacao' => $this->nullableString($item->observacao_snapshot),
        ];
    }

    /**
     * @return list<string>
     */
    protected function formatarEndereco(mixed $endereco): array
    {
        if (is_string($endereco)) {
            return collect(preg_split('/\R/u', trim($endereco)) ?: [])
                ->filter()
                ->values()
                ->all();
        }

        if (! is_array($endereco)) {
            return [];
        }

        $linhas = [];
        $logradouro = $this->nullableString($endereco['logradouro'] ?? $endereco['endereco'] ?? null);
        $numero = $this->nullableString($endereco['numero'] ?? null);
        $complemento = $this->nullableString($endereco['complemento'] ?? null);

        if ($logradouro) {
            $linhas[] = $logradouro
                .($numero ? ', '.$numero : '')
                .($complemento ? ' - '.$complemento : '');
        }

        $bairro = $this->nullableString($endereco['bairro'] ?? null);
        $cidade = $this->nullableString($endereco['cidade'] ?? null);
        $uf = $this->nullableString($endereco['uf'] ?? null);
        $localidade = implode(' - ', array_filter([
            $bairro,
            trim(($cidade ?? '').($uf ? '/'.$uf : '')) ?: null,
        ]));

        if ($localidade !== '') {
            $linhas[] = $localidade;
        }

        if ($cep = $this->nullableString($endereco['cep'] ?? null)) {
            $linhas[] = 'CEP '.$cep;
        }

        return $linhas;
    }

    protected function auditar(Romaneio $romaneio, ?User $usuario, string $filename): void
    {
        $reimpressao = RomaneioHistorico::query()
            ->where('romaneio_id', $romaneio->getKey())
            ->whereIn('evento', ['pdf_gerado', 'pdf_reimpresso'])
            ->exists();

        RomaneioHistorico::query()->create([
            'romaneio_id' => $romaneio->getKey(),
            'user_id' => $usuario?->getKey() ?? Auth::id(),
            'evento' => $reimpressao ? 'pdf_reimpresso' : 'pdf_gerado',
            'status_anterior' => $romaneio->status,
            'status_novo' => $romaneio->status,
            'metadados' => [
                'filename' => $filename,
                'exibir_valores' => (bool) $romaneio->exibir_valores_comerciais,
                'gerado_em' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function filename(Romaneio $romaneio): string
    {
        $codigo = Str::slug((string) ($romaneio->codigo ?: $romaneio->getKey()));
        $data = $romaneio->gerado_em?->format('Y-m-d') ?? now()->format('Y-m-d');

        return sprintf('romaneio-%s-%s.pdf', $codigo, $data);
    }

    protected function quantidade(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }

    protected function peso(mixed $value): string
    {
        return $this->quantidade($value).' kg';
    }

    protected function moeda(mixed $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    protected function formatarData(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : null;
    }

    protected function formatarDataHora(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y, H:i') : null;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
