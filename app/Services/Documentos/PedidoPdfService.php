<?php

namespace App\Services\Documentos;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\VendaHistorico;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedidoPdfService
{
    /** @var list<string> */
    protected const STATUS_PERMITIDOS = [
        VendaOperacaoPedido::STATUS_CONFIRMADA,
        VendaOperacaoPedido::STATUS_PARCIALMENTE_DESPACHADA,
        VendaOperacaoPedido::STATUS_DESPACHADA,
        VendaOperacaoPedido::STATUS_CONCLUIDA,
        VendaOperacaoPedido::STATUS_DEVOLVIDA_PARCIAL,
        VendaOperacaoPedido::STATUS_DEVOLVIDA,
    ];

    public function __construct(
        protected DocumentoConfiguracaoService $configuracoes,
        protected DocumentoImagemService $imagens,
        protected DocumentoPdfRenderer $renderer,
    ) {}

    public function gerar(VendaOperacaoPedido $pedido, ?User $usuario = null): DocumentoPdfGerado
    {
        $this->validarPedido($pedido);

        $usuarioEfetivo = $usuario ?? Auth::user();
        $incluirFotos = $usuarioEfetivo instanceof User
            && Gate::forUser($usuarioEfetivo)->allows('viewAttachments', $pedido);
        $relacoes = [
            'cliente',
            'user',
            'vendasOperacao.produto',
            'vendasOperacao.lotes',
        ];

        if ($incluirFotos) {
            $relacoes[] = 'fotos.vendaOperacao';
            $relacoes[] = 'fotos.user';
        }

        $pedido->loadMissing($relacoes);

        $configuracao = $this->configuracoes->obterValida();
        $empresa = $this->configuracoes->dadosParaDocumento($configuracao);
        $geradoEm = now();
        $dadosPedido = $this->mapearPedido($pedido, $geradoEm, $incluirFotos);
        $filename = $this->filename($pedido);
        $bytes = $this->renderer->render(
            'documentos.pedido',
            [
                'empresa' => $empresa,
                'documento' => [
                    'titulo' => 'Pedido de Venda',
                    'numero' => $dadosPedido['numero'],
                    'data' => $dadosPedido['data_pedido'],
                    'status' => $dadosPedido['status'],
                    'gerado_em' => $dadosPedido['impresso_em'],
                ],
                'pedido' => $dadosPedido,
            ],
            'Pedido de Venda - '.$dadosPedido['numero'],
        );

        $this->auditar($pedido, $usuario, $filename, count($dadosPedido['fotos']));

        return new DocumentoPdfGerado($bytes, $filename);
    }

    protected function validarPedido(VendaOperacaoPedido $pedido): void
    {
        if (! in_array($pedido->status, self::STATUS_PERMITIDOS, true)) {
            throw ValidationException::withMessages([
                'pedido' => 'O PDF está disponível somente após a confirmação do pedido.',
            ]);
        }

        if (! $pedido->vendasOperacao()->exists()) {
            throw ValidationException::withMessages([
                'pedido' => 'O pedido não possui produtos para gerar o documento.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearPedido(
        VendaOperacaoPedido $pedido,
        Carbon $geradoEm,
        bool $incluirFotos,
    ): array {
        $itens = $pedido->vendasOperacao
            ->map(fn (VendaOperacao $item): array => $this->mapearItem($item))
            ->values();

        $lotes = $itens
            ->pluck('lotes')
            ->flatten(1)
            ->values();

        $fotos = $incluirFotos
            ? $pedido->fotos
                ->map(function (object $foto): array {
                    $item = $foto->vendaOperacao;

                    return [
                        'data_uri' => $this->imagens->fotoParaPdf($foto),
                        'descricao' => $this->nullableString($foto->descricao),
                        'produto' => $this->nullableString($item?->produto_nome_snapshot),
                        'enviado_em' => $this->formatarDataHora($foto->created_at),
                        'enviado_por' => $this->nullableString($foto->user?->name),
                    ];
                })
                ->values()
            : new Collection;

        $cliente = $pedido->cliente;
        $clienteNome = $this->nullableString($pedido->cliente_nome_snapshot)
            ?? $this->nullableString($cliente?->razao_social)
            ?? $this->nullableString($cliente?->nome_fantasia)
            ?? 'Cliente não identificado';

        return [
            'numero' => $pedido->codigo ?: 'PED-'.$pedido->getKey(),
            'status' => VendaStatus::options()[$pedido->status] ?? Str::headline((string) $pedido->status),
            'data_pedido' => $this->formatarData($pedido->data_venda),
            'impresso_em' => $this->formatarDataHora($geradoEm),
            'vendedor' => $this->nullableString($pedido->vendedor_nome_snapshot)
                ?? $this->nullableString($pedido->user?->name),
            'cliente' => [
                'nome' => $clienteNome,
                'documento' => $this->nullableString($pedido->cliente_documento_snapshot)
                    ?? $this->nullableString($cliente?->cnpj),
                'telefone' => $this->nullableString($pedido->cliente_telefone_snapshot)
                    ?? $this->nullableString($cliente?->telefone),
                'email' => $this->nullableString($pedido->cliente_email_snapshot)
                    ?? $this->nullableString($cliente?->email),
                'pagamento' => $this->nullableString($pedido->condicao_pagamento_snapshot),
            ],
            'endereco_principal' => $this->formatarEndereco(
                $pedido->cliente_endereco_snapshot ?: $this->enderecoCliente($cliente),
            ),
            'endereco_entrega' => $this->formatarEndereco(
                $pedido->entrega_endereco_snapshot
                    ?: $pedido->cliente_endereco_snapshot
                    ?: $this->enderecoCliente($cliente),
            ),
            'itens' => $itens->all(),
            'lotes' => $lotes->all(),
            'fotos' => $fotos->all(),
            'total' => $this->moeda($pedido->receita_bruta_total ?: $itens->sum('subtotal_numerico')),
            'observacao' => $this->nullableString($pedido->observacao),
            'condicoes_comerciais' => $this->nullableString($pedido->condicoes_comerciais),
            'frete' => (float) $pedido->valor_frete_cobrado > 0
                ? $this->moeda($pedido->valor_frete_cobrado)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearItem(VendaOperacao $item): array
    {
        $quantidade = (float) $item->quantidade;
        $valorUnitario = (float) $item->preco_unitario;
        $subtotal = $item->receita_bruta !== null
            ? (float) $item->receita_bruta
            : round($quantidade * $valorUnitario, 2);
        $produto = $this->nullableString($item->produto_nome_snapshot)
            ?? $this->nullableString($item->produto?->nome)
            ?? 'Produto não identificado';

        $lotes = $item->lotes->map(fn (object $lote): array => [
            'produto' => $produto,
            'lote' => (string) $lote->numero_lote,
            'quantidade' => $this->quantidade($lote->quantidade),
            'fabricacao' => $lote->data_fabricacao
                ? $this->formatarData($lote->data_fabricacao)
                : ($lote->ano_fabricacao ? (string) $lote->ano_fabricacao : null),
            'validade' => $this->formatarData($lote->data_validade),
        ])->values()->all();

        return [
            'produto' => $produto,
            'quantidade' => $this->quantidade($quantidade),
            'unidade' => $this->nullableString($item->unidade_snapshot) ?? 'UN',
            'valor_unitario' => $this->moeda($valorUnitario),
            'subtotal' => $this->moeda($subtotal),
            'subtotal_numerico' => $subtotal,
            'observacao' => $this->nullableString($item->observacao),
            'lotes' => $lotes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function enderecoCliente(?object $cliente): ?array
    {
        if (! $cliente) {
            return null;
        }

        return [
            'logradouro' => $cliente->logradouro,
            'numero' => $cliente->numero,
            'complemento' => $cliente->complemento,
            'bairro' => $cliente->bairro,
            'cidade' => $cliente->cidade,
            'uf' => $cliente->uf,
            'cep' => $cliente->cep,
        ];
    }

    /**
     * @return list<string>
     */
    protected function formatarEndereco(mixed $endereco): array
    {
        if (is_string($endereco)) {
            return collect(preg_split('/\R/u', trim($endereco)) ?: [])
                ->filter(fn (string $linha): bool => $linha !== '')
                ->values()
                ->all();
        }

        if (! is_array($endereco)) {
            return [];
        }

        $linhas = new Collection;
        $logradouro = $this->nullableString($endereco['logradouro'] ?? $endereco['endereco'] ?? null);
        $numero = $this->nullableString($endereco['numero'] ?? null);
        $complemento = $this->nullableString($endereco['complemento'] ?? null);

        if ($logradouro) {
            $linha = $logradouro;
            $linha .= $numero ? ', '.$numero : '';
            $linha .= $complemento ? ' - '.$complemento : '';
            $linhas->push($linha);
        }

        $bairro = $this->nullableString($endereco['bairro'] ?? null);
        $cidade = $this->nullableString($endereco['cidade'] ?? null);
        $uf = $this->nullableString($endereco['uf'] ?? null);
        $localidade = implode(' - ', array_filter([
            $bairro,
            trim(($cidade ?? '').($uf ? '/'.$uf : '')) ?: null,
        ]));

        if ($localidade !== '') {
            $linhas->push($localidade);
        }

        $cep = $this->nullableString($endereco['cep'] ?? null);

        if ($cep) {
            $linhas->push('CEP '.$cep);
        }

        return $linhas->values()->all();
    }

    protected function auditar(
        VendaOperacaoPedido $pedido,
        ?User $usuario,
        string $filename,
        int $quantidadeFotos,
    ): void {
        $reimpressao = VendaHistorico::query()
            ->where('venda_operacao_pedido_id', $pedido->getKey())
            ->whereIn('evento', ['pdf_gerado', 'pdf_reimpresso'])
            ->exists();

        VendaHistorico::query()->create([
            'venda_operacao_pedido_id' => $pedido->getKey(),
            'user_id' => $usuario?->getKey() ?? Auth::id(),
            'evento' => $reimpressao ? 'pdf_reimpresso' : 'pdf_gerado',
            'status_anterior' => $pedido->status,
            'status_novo' => $pedido->status,
            'metadados' => [
                'filename' => $filename,
                'fotos_incluidas' => $quantidadeFotos,
                'gerado_em' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function filename(VendaOperacaoPedido $pedido): string
    {
        $codigo = Str::slug((string) ($pedido->codigo ?: $pedido->getKey()));
        $cliente = Str::slug((string) ($pedido->cliente_nome_snapshot ?: 'cliente'));
        $data = $pedido->data_venda?->format('Y-m-d') ?? now()->format('Y-m-d');

        return sprintf('pedido-%s-%s-%s.pdf', $codigo, $cliente, $data);
    }

    protected function quantidade(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }

    protected function moeda(mixed $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    protected function formatarData(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    protected function formatarDataHora(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('d/m/Y, H:i');
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
