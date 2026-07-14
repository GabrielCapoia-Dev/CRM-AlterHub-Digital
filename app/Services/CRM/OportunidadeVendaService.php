<?php

namespace App\Services\CRM;

use App\Enum\EtapaTipo;
use App\Models\Acesso\User;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\Produto;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OportunidadeVendaService
{
    public function __construct(
        protected VendaOperacaoService $vendaOperacaoService,
        protected VendaWorkflowService $workflowService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(int|Oportunidade $oportunidade): array
    {
        $oportunidade = $this->resolveOpportunity($oportunidade);

        return $this->buildPreview($oportunidade, lockProducts: false);
    }

    public function convert(int|Oportunidade $oportunidade, ?User $user = null): VendaOperacaoPedido
    {
        $oportunidadeId = $oportunidade instanceof Oportunidade ? $oportunidade->id : $oportunidade;

        return DB::transaction(function () use ($oportunidadeId, $user): VendaOperacaoPedido {
            $oportunidade = Oportunidade::query()
                ->with(['cliente', 'user', 'oportunidadeProdutos'])
                ->lockForUpdate()
                ->findOrFail($oportunidadeId);

            if ($oportunidade->venda_operacao_pedido_id) {
                return VendaOperacaoPedido::query()
                    ->with(['vendasOperacao.produto', 'cliente', 'user'])
                    ->findOrFail($oportunidade->venda_operacao_pedido_id);
            }

            $preview = $this->buildPreview($oportunidade, lockProducts: true);

            if (! $preview['can_convert']) {
                throw ValidationException::withMessages([
                    'saleConversion' => $preview['errors'],
                ]);
            }

            $actor = $user ?? $oportunidade->user;

            if (! $actor) {
                throw ValidationException::withMessages([
                    'user_id' => 'Defina um responsavel antes de converter a oportunidade.',
                ]);
            }

            $pedido = VendaOperacaoPedido::query()->create([
                'oportunidade_id' => $oportunidade->id,
                'cliente_id' => $oportunidade->cliente_id,
                'user_id' => $oportunidade->user_id ?? $actor->id,
                'status' => VendaOperacaoPedido::STATUS_RASCUNHO,
                'data_venda' => now()->toDateString(),
                'cliente_nome_snapshot' => $oportunidade->cliente?->razao_social,
                'cliente_documento_snapshot' => $oportunidade->cliente?->cnpj,
                'vendedor_nome_snapshot' => $oportunidade->user?->name ?? $user?->name,
                'observacao' => "Venda gerada a partir da oportunidade #{$oportunidade->id}.",
            ]);
            $pedido->assignCodigo();

            $linksById = $oportunidade->oportunidadeProdutos->keyBy('id');

            foreach ($preview['items'] as $item) {
                $linha = $this->vendaOperacaoService->create([
                    'produto_id' => $item['produto_id'],
                    'data_venda' => $pedido->data_venda?->toDateString() ?? now()->toDateString(),
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'icms_aliquota' => 0,
                    'outros_impostos_aliquota' => 0,
                    'cliente_nome' => $pedido->cliente_nome_snapshot,
                    'vendedor_nome' => $pedido->vendedor_nome_snapshot,
                    'observacao' => "Venda gerada pela oportunidade {$oportunidade->titulo}.",
                ], $actor, $pedido, false);

                $link = $linksById->get($item['oportunidade_produto_id']);

                if ($linha->desconto_requer_aprovacao
                    && $link?->desconto_aprovado_por
                    && $link?->desconto_aprovado_em) {
                    $linha->forceFill([
                        'desconto_aprovado_por' => $link->desconto_aprovado_por,
                        'desconto_aprovado_em' => $link->desconto_aprovado_em,
                    ])->save();
                }
            }

            $this->vendaOperacaoService->refreshPedidoTotals($pedido);
            $pedido = $this->workflowService->confirmar(
                $pedido,
                $actor,
                "converter:oportunidade:{$oportunidade->id}",
            );
            if ($pedido->status === VendaOperacaoPedido::STATUS_CONFIRMADA) {
                $this->markOpportunityAsConverted($oportunidade, $pedido);
            } else {
                $oportunidade->forceFill([
                    'venda_operacao_pedido_id' => $pedido->id,
                ])->save();
            }

            return $pedido->fresh([
                'oportunidade',
                'cliente',
                'user',
                'vendasOperacao.produto',
                'vendasOperacao.produtoMovimentacao',
            ]);
        });
    }

    protected function resolveOpportunity(int|Oportunidade $oportunidade): Oportunidade
    {
        if ($oportunidade instanceof Oportunidade) {
            return $oportunidade->loadMissing(['cliente', 'user', 'oportunidadeProdutos']);
        }

        return Oportunidade::query()
            ->with(['cliente', 'user', 'oportunidadeProdutos'])
            ->findOrFail($oportunidade);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPreview(Oportunidade $oportunidade, bool $lockProducts): array
    {
        $errors = [];
        $links = $oportunidade->oportunidadeProdutos;

        if ($oportunidade->venda_operacao_pedido_id || $oportunidade->convertida_em) {
            $errors[] = 'Esta oportunidade ja foi convertida em venda.';
        }

        if ($links->isEmpty()) {
            $errors[] = 'Vincule pelo menos um produto antes de transformar a oportunidade em venda.';
        }

        $produtos = $this->loadProductsForLinks($links, $lockProducts);
        $requiredByProduct = $this->requiredQuantitiesByProduct($links);

        $items = $links
            ->values()
            ->map(function ($link) use ($produtos, $requiredByProduct, &$errors): array {
                $produto = $produtos->get($link->produto_id);
                $rowErrors = [];
                $quantidade = round((float) ($link->quantidade ?? 0), 4);
                $precoInfo = $this->resolveUnitPrice($link, $produto);
                $estoqueAtual = $produto ? $produto->estoqueDisponivel() : 0.0;
                $quantidadeNecessaria = (float) ($requiredByProduct->get($link->produto_id, 0.0));

                if (! $produto) {
                    $rowErrors[] = 'Produto nao encontrado.';
                } else {
                    if ($produto->status !== 'ativo' || ! $produto->ativo) {
                        $rowErrors[] = 'Produto inativo ou indisponivel.';
                    }

                }

                if ($quantidade <= 0) {
                    $rowErrors[] = 'Quantidade precisa ser maior que zero.';
                }

                if ($precoInfo['preco_unitario'] <= 0) {
                    $rowErrors[] = 'Informe preco negociado ou preco de tabela maior que zero.';
                }

                $precoMinimo = $produto?->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;
                if (
                    $produto
                    && $precoMinimo > 0
                    && $precoInfo['preco_unitario'] > 0
                    && $precoInfo['preco_unitario'] < $precoMinimo
                    && ! $link->desconto_aprovado_por
                ) {
                    $rowErrors[] = 'Desconto abaixo do preco minimo ainda nao foi aprovado no CRM.';
                }

                foreach ($rowErrors as $rowError) {
                    $errors[] = ($produto?->nome ?? 'Produto removido').': '.$rowError;
                }

                return [
                    'oportunidade_produto_id' => $link->id,
                    'produto_id' => $link->produto_id,
                    'produto_codigo' => $produto?->codigo_interno,
                    'produto_nome' => $produto?->nome ?? 'Produto removido',
                    'unidade' => $produto?->unidade_medida ?: 'un',
                    'quantidade' => $quantidade,
                    'quantidade_total_produto' => round($quantidadeNecessaria, 4),
                    'estoque_atual' => round($estoqueAtual, 4),
                    'preco_unitario' => $precoInfo['preco_unitario'],
                    'preco_origem' => $precoInfo['preco_origem'],
                    'subtotal' => round($quantidade * $precoInfo['preco_unitario'], 2),
                    'errors' => $rowErrors,
                    'ok' => $rowErrors === [],
                ];
            })
            ->all();

        $receitaBruta = round((float) collect($items)->sum('subtotal'), 2);
        $quantidadeTotal = round((float) collect($items)->sum('quantidade'), 4);

        return [
            'can_convert' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'opportunity' => [
                'id' => $oportunidade->id,
                'titulo' => $oportunidade->titulo,
                'cliente' => $oportunidade->cliente?->razao_social,
                'documento' => $oportunidade->cliente?->cnpj,
                'responsavel' => $oportunidade->user?->name,
            ],
            'items' => $items,
            'totals' => [
                'itens_count' => count($items),
                'quantidade_total' => $quantidadeTotal,
                'receita_bruta' => $receitaBruta,
            ],
        ];
    }

    /**
     * @param  Collection<int, mixed>  $links
     * @return Collection<int, Produto>
     */
    protected function loadProductsForLinks(Collection $links, bool $lockProducts): Collection
    {
        $productIds = $links->pluck('produto_id')->filter()->unique()->values()->all();

        $query = Produto::query()
            ->with(['categoriaProduto', 'produtoMovimentacoes'])
            ->whereKey($productIds);

        if ($lockProducts) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, mixed>  $links
     * @return Collection<int, float>
     */
    protected function requiredQuantitiesByProduct(Collection $links): Collection
    {
        return $links
            ->groupBy('produto_id')
            ->map(fn (Collection $items): float => round((float) $items->sum(fn ($item): float => (float) ($item->quantidade ?? 0)), 4));
    }

    /**
     * @return array{preco_unitario:float,preco_origem:string}
     */
    protected function resolveUnitPrice($link, ?Produto $produto): array
    {
        $negociado = $link->preco_negociado !== null ? (float) $link->preco_negociado : 0.0;

        if ($negociado > 0) {
            return [
                'preco_unitario' => round($negociado, 4),
                'preco_origem' => 'negociado',
            ];
        }

        $tabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;

        return [
            'preco_unitario' => round($tabela, 4),
            'preco_origem' => 'tabela',
        ];
    }

    protected function markOpportunityAsConverted(Oportunidade $oportunidade, VendaOperacaoPedido $pedido): void
    {
        $wonStage = $this->resolveWonStage();

        $oportunidade->markSaleAsConverted($pedido, $wonStage);
    }

    protected function resolveWonStage(): ?Etapa
    {
        return Etapa::query()
            ->where(function ($query): void {
                $query
                    ->where('tipo', EtapaTipo::Ganha->value)
                    ->orWhereIn('slug', ['ganho', 'win'])
                    ->orWhereRaw('lower(nome) in (?, ?)', ['ganho', 'win']);
            })
            ->orderByDesc('fechamento')
            ->orderBy('ordem')
            ->first();
    }
}
