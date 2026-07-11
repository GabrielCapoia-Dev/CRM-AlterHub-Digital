<?php

namespace App\Services\Operacao;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaOperacaoService
{
    public function __construct(
        protected OperacaoAnalyticsService $analyticsService,
        protected MovimentacaoEstoqueService $movimentacaoEstoqueService,
    ) {}

    public function create(array $data, ?User $user = null, ?VendaOperacaoPedido $pedido = null, bool $deductStock = true): VendaOperacao
    {
        return DB::transaction(function () use ($data, $user, $pedido, $deductStock): VendaOperacao {
            $produto = Produto::query()
                ->with(['categoriaProduto', 'produtoMovimentacoes'])
                ->lockForUpdate()
                ->findOrFail($data['produto_id']);

            $payload = $this->normalizePayload($data);
            $snapshot = $this->analyticsService->productStockSnapshot($produto);
            $pricing = $this->resolvePricing($produto, $payload['preco_unitario']);

            $this->validate($produto, $payload, $snapshot['estoque_atual'], $deductStock);

            $financials = $this->calculateFinancials(
                $payload['quantidade'],
                $payload['preco_unitario'],
                $payload['icms_aliquota'],
                $payload['outros_impostos_aliquota'],
                $snapshot['custo_medio'],
            );

            $movimentacaoId = null;

            if ($deductStock) {
                $movimentacao = $this->movimentacaoEstoqueService->createForProduto([
                    'produto_id' => $produto->id,
                    'tipo' => 'saida',
                    'quantidade' => $payload['quantidade'],
                    'motivo' => 'Venda operacional',
                    'origem_destino' => 'Venda operacional',
                    'destino' => $payload['cliente_nome'],
                    'realizado_em' => $payload['data_venda'],
                    'observacao' => $payload['observacao'],
                ], $user);

                $movimentacaoId = $movimentacao->id;
            }

            $venda = VendaOperacao::query()->create([
                'user_id' => $user?->id,
                'venda_operacao_pedido_id' => $pedido?->id,
                'produto_id' => $produto->id,
                'produto_movimentacao_id' => $movimentacaoId,
                'produto_codigo_snapshot' => $produto->codigo_interno,
                'produto_nome_snapshot' => $produto->nome,
                'produto_categoria_snapshot' => $produto->categoriaProduto?->nome,
                'unidade_snapshot' => $produto->unidade_medida,
                'data_venda' => $payload['data_venda'],
                'quantidade' => $payload['quantidade'],
                'preco_unitario' => $payload['preco_unitario'],
                'preco_tabela_snapshot' => $pricing['preco_tabela'],
                'preco_minimo_snapshot' => $pricing['preco_minimo'],
                'desconto_percentual' => $pricing['desconto_percentual'],
                'desconto_requer_aprovacao' => $pricing['requer_aprovacao'],
                'desconto_aprovado_por' => null,
                'desconto_aprovado_em' => null,
                'receita_bruta' => $financials['receita_bruta'],
                'custo_unitario_snapshot' => $financials['custo_unitario'],
                'custo_total_snapshot' => $financials['custo_total'],
                'icms_aliquota' => $payload['icms_aliquota'],
                'icms_valor' => $financials['icms_valor'],
                'outros_impostos_aliquota' => $payload['outros_impostos_aliquota'],
                'outros_impostos_valor' => $financials['outros_impostos_valor'],
                'receita_liquida' => $financials['receita_liquida'],
                'lucro_bruto' => $financials['lucro_bruto'],
                'lucro_apos_impostos' => $financials['lucro_bruto'],
                'cliente_nome' => $payload['cliente_nome'],
                'vendedor_nome' => $payload['vendedor_nome'],
                'observacao' => $payload['observacao'],
            ]);

            if ($deductStock && $movimentacaoId) {
                $venda->load('produtoMovimentacao');
                $venda->produtoMovimentacao?->update([
                    'documento_referencia' => $pedido?->codigo ?: sprintf('VEN-%05d', $venda->id),
                ]);
            }

            if ($pedido) {
                $this->refreshPedidoTotals($pedido);
            }

            return $venda->fresh(['produto', 'user', 'produtoMovimentacao']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPedido(array $data, ?User $user = null): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($data, $user): VendaOperacaoPedido {
            $items = $this->extractItems($data);

            if ($items === []) {
                throw ValidationException::withMessages([
                    'itens' => 'Informe pelo menos um produto para a venda.',
                ]);
            }

            $header = $this->normalizeHeader($data, $user);
            $preparedItems = [];
            $requiresApproval = false;

            foreach ($items as $index => $item) {
                $produto = Produto::query()
                    ->with(['categoriaProduto', 'produtoMovimentacoes'])
                    ->lockForUpdate()
                    ->find($item['produto_id'] ?? null);

                if (! $produto) {
                    throw ValidationException::withMessages([
                        "itens.{$index}.produto_id" => 'Selecione um produto valido.',
                    ]);
                }

                $payload = $this->normalizePayload(array_merge($header, $item));
                $pricing = $this->resolvePricing($produto, $payload['preco_unitario']);
                $snapshot = $this->analyticsService->productStockSnapshot($produto);

                // Estoque e regras de produto: hard quando ativo; em pendente ainda valida produto/preco/qtd.
                $this->validate($produto, $payload, $snapshot['estoque_atual'], deductStock: false);

                if ($pricing['requer_aprovacao']) {
                    $requiresApproval = true;
                }

                $preparedItems[] = [
                    'produto' => $produto,
                    'payload' => $payload,
                    'pricing' => $pricing,
                    'snapshot' => $snapshot,
                ];
            }

            $status = $requiresApproval
                ? VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO
                : VendaOperacaoPedido::STATUS_ATIVA;

            if ($status === VendaOperacaoPedido::STATUS_ATIVA) {
                foreach ($preparedItems as $index => $prepared) {
                    $this->validate(
                        $prepared['produto'],
                        $prepared['payload'],
                        $prepared['snapshot']['estoque_atual'],
                        deductStock: true,
                    );
                }
            }

            $pedido = VendaOperacaoPedido::query()->create([
                'cliente_id' => $header['cliente_id'],
                'user_id' => $user?->id,
                'origem_pedido_id' => $header['origem_pedido_id'],
                'status' => $status,
                'data_venda' => $header['data_venda'],
                'cliente_nome_snapshot' => $header['cliente_nome'],
                'cliente_documento_snapshot' => $header['cliente_documento'],
                'vendedor_nome_snapshot' => $header['vendedor_nome'],
                'observacao' => $header['observacao'],
            ]);
            $pedido->assignCodigo();

            $deductStock = $status === VendaOperacaoPedido::STATUS_ATIVA;

            foreach ($preparedItems as $prepared) {
                $this->createLineFromPrepared($prepared, $user, $pedido, $deductStock);
            }

            return $this->refreshPedidoTotals($pedido)
                ->fresh(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'cliente', 'user']);
        });
    }

    public function approve(VendaOperacaoPedido $pedido, User $approver): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($pedido, $approver): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->with(['vendasOperacao.produto.produtoMovimentacoes', 'vendasOperacao.produto.categoriaProduto'])
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if (! $pedido->isPendenteAprovacao()) {
                throw ValidationException::withMessages([
                    'status' => 'Somente vendas pendentes de aprovacao podem ser autorizadas.',
                ]);
            }

            foreach ($pedido->vendasOperacao as $index => $linha) {
                $produto = Produto::query()
                    ->with(['produtoMovimentacoes'])
                    ->lockForUpdate()
                    ->find($linha->produto_id);

                if (! $produto) {
                    throw ValidationException::withMessages([
                        "itens.{$index}.produto_id" => 'Produto da linha nao encontrado.',
                    ]);
                }

                $estoqueAtual = (float) ($produto->estoqueAtual() ?? 0);
                $quantidade = (float) $linha->quantidade;

                if ($produto->status !== 'ativo' || ! $produto->ativo) {
                    throw ValidationException::withMessages([
                        "itens.{$index}.produto_id" => "O produto {$produto->nome} nao esta disponivel.",
                    ]);
                }

                if ($quantidade > $estoqueAtual) {
                    throw ValidationException::withMessages([
                        "itens.{$index}.quantidade" => "Estoque insuficiente para o produto {$produto->nome}.",
                    ]);
                }

                $snapshot = $this->analyticsService->productStockSnapshot($produto);
                $financials = $this->calculateFinancials(
                    $quantidade,
                    (float) $linha->preco_unitario,
                    (float) $linha->icms_aliquota,
                    (float) $linha->outros_impostos_aliquota,
                    $snapshot['custo_medio'],
                );

                $movimentacao = $this->movimentacaoEstoqueService->createForProduto([
                    'produto_id' => $produto->id,
                    'tipo' => 'saida',
                    'quantidade' => $quantidade,
                    'motivo' => 'Venda operacional',
                    'origem_destino' => 'Venda operacional',
                    'destino' => $linha->cliente_nome,
                    'realizado_em' => $linha->data_venda?->toDateString() ?? now()->toDateString(),
                    'observacao' => $linha->observacao,
                    'documento_referencia' => $pedido->codigo ?: sprintf('VEN-%05d', $linha->id),
                ], $approver);

                $linha->forceFill([
                    'produto_movimentacao_id' => $movimentacao->id,
                    'custo_unitario_snapshot' => $financials['custo_unitario'],
                    'custo_total_snapshot' => $financials['custo_total'],
                    'icms_valor' => $financials['icms_valor'],
                    'outros_impostos_valor' => $financials['outros_impostos_valor'],
                    'receita_bruta' => $financials['receita_bruta'],
                    'receita_liquida' => $financials['receita_liquida'],
                    'lucro_bruto' => $financials['lucro_bruto'],
                    'lucro_apos_impostos' => $financials['lucro_bruto'],
                    'desconto_aprovado_por' => $linha->desconto_requer_aprovacao ? $approver->id : $linha->desconto_aprovado_por,
                    'desconto_aprovado_em' => $linha->desconto_requer_aprovacao ? now() : $linha->desconto_aprovado_em,
                ])->save();
            }

            $pedido->forceFill([
                'status' => VendaOperacaoPedido::STATUS_ATIVA,
                'aprovado_por' => $approver->id,
                'aprovado_em' => now(),
                'motivo_recusa' => null,
            ])->save();

            return $this->refreshPedidoTotals($pedido)
                ->fresh(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'aprovadoPor', 'cliente', 'user']);
        });
    }

    public function reject(VendaOperacaoPedido $pedido, User $approver, ?string $motivo = null): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($pedido, $approver, $motivo): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if (! $pedido->isPendenteAprovacao()) {
                throw ValidationException::withMessages([
                    'status' => 'Somente vendas pendentes de aprovacao podem ser recusadas.',
                ]);
            }

            $pedido->forceFill([
                'status' => VendaOperacaoPedido::STATUS_RECUSADA,
                'aprovado_por' => $approver->id,
                'aprovado_em' => now(),
                'motivo_recusa' => $this->normalizeString($motivo),
            ])->save();

            return $pedido->fresh(['vendasOperacao', 'aprovadoPor', 'cliente', 'user']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCopyPayload(VendaOperacaoPedido $origem): array
    {
        $origem->unsetRelation('vendasOperacao');
        $origem->load(['vendasOperacao.produto', 'cliente']);

        $itens = $origem->vendasOperacao
            ->map(function (VendaOperacao $linha): array {
                $produto = $linha->produto;
                $precoTabela = $produto?->preco_tabela !== null
                    ? (float) $produto->preco_tabela
                    : (float) ($linha->preco_tabela_snapshot ?? $linha->preco_unitario);

                return [
                    'produto_id' => $linha->produto_id,
                    'quantidade' => (float) $linha->quantidade,
                    'preco_unitario' => round($precoTabela, 2),
                    'icms_aliquota' => (float) ($linha->icms_aliquota ?? 0),
                    'outros_impostos_aliquota' => (float) ($linha->outros_impostos_aliquota ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'cliente_id' => $origem->cliente_id,
            'data_venda' => now()->toDateString(),
            'vendedor_nome' => auth()->user()?->name,
            'observacao' => null,
            'origem_pedido_id' => $origem->id,
            'itens' => $itens,
        ];
    }

    public function itemRequiresApproval(Produto $produto, float $precoUnitario): bool
    {
        return $this->resolvePricing($produto, $precoUnitario)['requer_aprovacao'];
    }

    public function podeVerTodasVendas(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(RolesEnum::SuperAdmin->value) || $user->hasRole(RolesEnum::Admin->value)) {
            return true;
        }

        return $user->hasPermissionTo(PermissoesEnum::AprovarDesconto->value);
    }

    public function queryPorPerfil(?User $user): Builder
    {
        $query = VendaOperacaoPedido::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->podeVerTodasVendas($user)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function refreshPedidoTotals(VendaOperacaoPedido $pedido): VendaOperacaoPedido
    {
        $linhas = $pedido->vendasOperacao()->get();

        $pedido->forceFill([
            'itens_count' => $linhas->count(),
            'quantidade_total' => round((float) $linhas->sum('quantidade'), 4),
            'receita_bruta_total' => round((float) $linhas->sum('receita_bruta'), 2),
            'receita_liquida_total' => round((float) $linhas->sum('receita_liquida'), 2),
            'custo_total_snapshot' => round((float) $linhas->sum('custo_total_snapshot'), 2),
            'lucro_bruto_total' => round((float) $linhas->sum('lucro_bruto'), 2),
            'lucro_apos_impostos_total' => round((float) $linhas->sum('lucro_apos_impostos'), 2),
        ])->save();

        return $pedido;
    }

    /**
     * @param  array{produto:Produto,payload:array<string,mixed>,pricing:array<string,mixed>,snapshot:array<string,mixed>}  $prepared
     */
    protected function createLineFromPrepared(array $prepared, ?User $user, VendaOperacaoPedido $pedido, bool $deductStock): VendaOperacao
    {
        $produto = $prepared['produto'];
        $payload = $prepared['payload'];
        $pricing = $prepared['pricing'];
        $snapshot = $prepared['snapshot'];

        $financials = $this->calculateFinancials(
            $payload['quantidade'],
            $payload['preco_unitario'],
            $payload['icms_aliquota'],
            $payload['outros_impostos_aliquota'],
            $snapshot['custo_medio'],
        );

        $movimentacaoId = null;

        if ($deductStock) {
            $movimentacao = $this->movimentacaoEstoqueService->createForProduto([
                'produto_id' => $produto->id,
                'tipo' => 'saida',
                'quantidade' => $payload['quantidade'],
                'motivo' => 'Venda operacional',
                'origem_destino' => 'Venda operacional',
                'destino' => $payload['cliente_nome'],
                'realizado_em' => $payload['data_venda'],
                'observacao' => $payload['observacao'],
                'documento_referencia' => $pedido->codigo,
            ], $user);

            $movimentacaoId = $movimentacao->id;
        }

        return VendaOperacao::query()->create([
            'user_id' => $user?->id,
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'produto_movimentacao_id' => $movimentacaoId,
            'produto_codigo_snapshot' => $produto->codigo_interno,
            'produto_nome_snapshot' => $produto->nome,
            'produto_categoria_snapshot' => $produto->categoriaProduto?->nome,
            'unidade_snapshot' => $produto->unidade_medida,
            'data_venda' => $payload['data_venda'],
            'quantidade' => $payload['quantidade'],
            'preco_unitario' => $payload['preco_unitario'],
            'preco_tabela_snapshot' => $pricing['preco_tabela'],
            'preco_minimo_snapshot' => $pricing['preco_minimo'],
            'desconto_percentual' => $pricing['desconto_percentual'],
            'desconto_requer_aprovacao' => $pricing['requer_aprovacao'],
            'desconto_aprovado_por' => null,
            'desconto_aprovado_em' => null,
            'receita_bruta' => $financials['receita_bruta'],
            'custo_unitario_snapshot' => $financials['custo_unitario'],
            'custo_total_snapshot' => $financials['custo_total'],
            'icms_aliquota' => $payload['icms_aliquota'],
            'icms_valor' => $financials['icms_valor'],
            'outros_impostos_aliquota' => $payload['outros_impostos_aliquota'],
            'outros_impostos_valor' => $financials['outros_impostos_valor'],
            'receita_liquida' => $financials['receita_liquida'],
            'lucro_bruto' => $financials['lucro_bruto'],
            'lucro_apos_impostos' => $financials['lucro_bruto'],
            'cliente_nome' => $payload['cliente_nome'],
            'vendedor_nome' => $payload['vendedor_nome'],
            'observacao' => $payload['observacao'],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractItems(array $data): array
    {
        if (isset($data['itens']) && is_array($data['itens'])) {
            return array_values(array_filter(
                $data['itens'],
                fn ($item): bool => is_array($item) && filled($item['produto_id'] ?? null),
            ));
        }

        if (filled($data['produto_id'] ?? null)) {
            return [[
                'produto_id' => $data['produto_id'],
                'quantidade' => $data['quantidade'] ?? 0,
                'preco_unitario' => $data['preco_unitario'] ?? 0,
                'icms_aliquota' => $data['icms_aliquota'] ?? 0,
                'outros_impostos_aliquota' => $data['outros_impostos_aliquota'] ?? 0,
            ]];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeHeader(array $data, ?User $user): array
    {
        $cliente = $this->resolveCliente($data['cliente_id'] ?? null);

        return [
            'data_venda' => $data['data_venda'] ?? now()->toDateString(),
            'cliente_id' => $cliente?->id,
            'cliente_nome' => $cliente?->razao_social ?? $this->normalizeString($data['cliente_nome'] ?? null),
            'cliente_documento' => $cliente?->cnpj,
            'vendedor_nome' => $this->normalizeString($data['vendedor_nome'] ?? null) ?? $user?->name,
            'observacao' => $this->normalizeString($data['observacao'] ?? null),
            'origem_pedido_id' => filled($data['origem_pedido_id'] ?? null) ? (int) $data['origem_pedido_id'] : null,
        ];
    }

    /**
     * @return array{preco_tabela:float,preco_minimo:float,desconto_percentual:float,requer_aprovacao:bool}
     */
    protected function resolvePricing(Produto $produto, float $precoUnitario): array
    {
        $precoTabela = $produto->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;
        $precoMinimo = $produto->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;
        $desconto = 0.0;

        if ($precoTabela > 0 && $precoUnitario > 0) {
            $desconto = round(max(0, min(100, (1 - ($precoUnitario / $precoTabela)) * 100)), 2);
        }

        return [
            'preco_tabela' => round($precoTabela, 2),
            'preco_minimo' => round($precoMinimo, 2),
            'desconto_percentual' => $desconto,
            'requer_aprovacao' => $precoMinimo > 0 && $precoUnitario > 0 && $precoUnitario < $precoMinimo,
        ];
    }

    /**
     * @return array{receita_bruta:float,icms_valor:float,outros_impostos_valor:float,receita_liquida:float,custo_unitario:float,custo_total:float,lucro_bruto:float}
     */
    protected function calculateFinancials(
        float $quantidade,
        float $precoUnitario,
        float $icmsAliquota,
        float $outrosImpostosAliquota,
        float $custoUnitario,
    ): array {
        $receitaBruta = round($quantidade * $precoUnitario, 2);
        $icmsValor = round($receitaBruta * ($icmsAliquota / 100), 2);
        $outrosImpostosValor = round($receitaBruta * ($outrosImpostosAliquota / 100), 2);
        $receitaLiquida = round($receitaBruta - $icmsValor - $outrosImpostosValor, 2);
        $custoTotal = round($custoUnitario * $quantidade, 2);
        $lucroBruto = round($receitaLiquida - $custoTotal, 2);

        return [
            'receita_bruta' => $receitaBruta,
            'icms_valor' => $icmsValor,
            'outros_impostos_valor' => $outrosImpostosValor,
            'receita_liquida' => $receitaLiquida,
            'custo_unitario' => $custoUnitario,
            'custo_total' => $custoTotal,
            'lucro_bruto' => $lucroBruto,
        ];
    }

    protected function validate(Produto $produto, array $payload, float $estoqueAtual, bool $deductStock): void
    {
        $messages = [];

        if ($payload['quantidade'] <= 0) {
            $messages['quantidade'] = 'Informe uma quantidade maior que zero.';
        }

        if ($payload['preco_unitario'] <= 0) {
            $messages['preco_unitario'] = 'Informe um preco unitario maior que zero.';
        }

        if ($produto->status !== 'ativo' || ! $produto->ativo) {
            $messages['produto_id'] = 'O produto precisa estar disponivel para registrar vendas.';
        }

        if ($deductStock && $payload['quantidade'] > $estoqueAtual) {
            $messages['quantidade'] = 'A quantidade informada ultrapassa o saldo atual disponivel do produto.';
        }

        if ($payload['icms_aliquota'] < 0 || $payload['outros_impostos_aliquota'] < 0) {
            $messages['icms_aliquota'] = 'As aliquotas nao podem ser negativas.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function normalizePayload(array $data): array
    {
        $cliente = $this->resolveCliente($data['cliente_id'] ?? null);

        return [
            'data_venda' => $data['data_venda'] ?? now()->toDateString(),
            'quantidade' => round((float) ($data['quantidade'] ?? 0), 4),
            'preco_unitario' => round((float) ($data['preco_unitario'] ?? 0), 4),
            'icms_aliquota' => round((float) ($data['icms_aliquota'] ?? 0), 2),
            'outros_impostos_aliquota' => round((float) ($data['outros_impostos_aliquota'] ?? 0), 2),
            'cliente_id' => $cliente?->id,
            'cliente_nome' => $cliente?->razao_social ?? $this->normalizeString($data['cliente_nome'] ?? null),
            'cliente_documento' => $cliente?->cnpj,
            'vendedor_nome' => $this->normalizeString($data['vendedor_nome'] ?? null),
            'observacao' => $this->normalizeString($data['observacao'] ?? null),
        ];
    }

    protected function resolveCliente(mixed $clienteId): ?Cliente
    {
        if (blank($clienteId)) {
            return null;
        }

        $cliente = Cliente::query()->find((int) $clienteId);

        if (! $cliente) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Selecione um cliente cadastrado valido.',
            ]);
        }

        return $cliente;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
