<?php

namespace App\Services\Operacao;

use App\Enum\EtapaTipo;
use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\Produto;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Acesso\RoleService;
use App\Services\Produtos\EstoqueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaOperacaoService
{
    public function __construct(
        protected OperacaoAnalyticsService $analyticsService,
        protected VendaWorkflowService $workflowService,
        protected EstoqueService $estoqueService,
    ) {}

    public function create(array $data, ?User $user = null, ?VendaOperacaoPedido $pedido = null, bool $deductStock = false): VendaOperacao
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

            $venda = VendaOperacao::query()->create([
                'user_id' => $user?->id,
                'venda_operacao_pedido_id' => $pedido?->id,
                'produto_id' => $produto->id,
                'produto_movimentacao_id' => null,
                'produto_codigo_snapshot' => $produto->codigo_interno,
                'produto_nome_snapshot' => $produto->nome,
                'produto_categoria_snapshot' => $produto->categoriaProduto?->nome,
                'unidade_snapshot' => $produto->unidade_medida,
                'peso_unitario_kg_snapshot' => $produto->peso_unitario_kg,
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
                : VendaOperacaoPedido::STATUS_RASCUNHO;

            $pedido = VendaOperacaoPedido::query()->create([
                'cliente_id' => $header['cliente_id'],
                'user_id' => $header['vendedor_user_id'],
                'origem_pedido_id' => $header['origem_pedido_id'],
                'status' => $status,
                'data_venda' => $header['data_venda'],
                'cliente_nome_snapshot' => $header['cliente_nome'],
                'cliente_documento_snapshot' => $header['cliente_documento'],
                'cliente_telefone_snapshot' => $header['cliente_telefone'],
                'cliente_email_snapshot' => $header['cliente_email'],
                'cliente_endereco_snapshot' => $header['cliente_endereco'],
                'entrega_endereco_snapshot' => $header['entrega_endereco'],
                'condicao_pagamento_snapshot' => $header['condicao_pagamento'],
                'condicoes_comerciais' => $header['condicoes_comerciais'],
                'quantidade_volumes' => $header['quantidade_volumes'],
                'vendedor_nome_snapshot' => $header['vendedor_nome'],
                'observacao' => $header['observacao'],
            ]);
            $pedido->assignCodigo();

            $vendedorUser = $header['vendedor_user'];

            foreach ($preparedItems as $prepared) {
                $this->createLineFromPrepared($prepared, $vendedorUser ?? $user, $pedido, false);
            }

            $faltas = $this->estoqueService->faltasVenda($pedido, bloquear: true);
            $motivos = $this->buildApprovalReasons($requiresApproval, $faltas);

            $pedido->forceFill([
                'status' => $motivos
                    ? VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO
                    : VendaOperacaoPedido::STATUS_RASCUNHO,
                'motivos_aprovacao' => $motivos,
            ])->save();

            return $this->refreshPedidoTotals($pedido)
                ->fresh(['vendasOperacao.produto', 'vendasOperacao.produtoMovimentacao', 'cliente', 'user']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePedido(VendaOperacaoPedido $pedido, array $data, User $user): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($pedido, $data, $user): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if (! $pedido->canEditCommercially()) {
                throw ValidationException::withMessages([
                    'status' => 'Somente rascunhos e vendas pendentes de aprovacao podem ser editados.',
                ]);
            }

            $this->assertSemDependenciasDocumentais($pedido);

            $items = $this->extractItems($data);

            if ($items === []) {
                throw ValidationException::withMessages(['itens' => 'Informe pelo menos um produto para a venda.']);
            }

            $header = $this->normalizeHeader($data, $user);
            $statusAnterior = $pedido->status;
            $pedido->vendasOperacao()->delete();
            $requiresApproval = false;

            $pedido->forceFill([
                'cliente_id' => $header['cliente_id'],
                'user_id' => $header['vendedor_user_id'],
                'data_venda' => $header['data_venda'],
                'cliente_nome_snapshot' => $header['cliente_nome'],
                'cliente_documento_snapshot' => $header['cliente_documento'],
                'cliente_telefone_snapshot' => $header['cliente_telefone'],
                'cliente_email_snapshot' => $header['cliente_email'],
                'cliente_endereco_snapshot' => $header['cliente_endereco'],
                'entrega_endereco_snapshot' => $header['entrega_endereco'],
                'condicao_pagamento_snapshot' => $header['condicao_pagamento'],
                'condicoes_comerciais' => $header['condicoes_comerciais'],
                'quantidade_volumes' => $header['quantidade_volumes'],
                'vendedor_nome_snapshot' => $header['vendedor_nome'],
                'observacao' => $header['observacao'],
                'aprovado_por' => null,
                'aprovado_em' => null,
                'motivo_recusa' => null,
            ])->save();

            foreach ($items as $index => $item) {
                $produto = Produto::query()->find($item['produto_id'] ?? null);

                if (! $produto) {
                    throw ValidationException::withMessages(["itens.{$index}.produto_id" => 'Selecione um produto valido.']);
                }

                $payload = array_merge($header, $item);
                $linha = $this->create($payload, $header['vendedor_user'] ?? $user, $pedido, false);
                $requiresApproval = $requiresApproval || $linha->desconto_requer_aprovacao;
            }

            $faltas = $this->estoqueService->faltasVenda($pedido, bloquear: true);
            $motivos = $this->buildApprovalReasons($requiresApproval, $faltas);

            $pedido->forceFill([
                'status' => $motivos
                    ? VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO
                    : VendaOperacaoPedido::STATUS_RASCUNHO,
                'motivos_aprovacao' => $motivos,
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $user,
                'dados_comerciais_atualizados',
                $statusAnterior,
                $pedido->status,
            );

            return $this->refreshPedidoTotals($pedido)
                ->fresh(['vendasOperacao.produto', 'cliente', 'user']);
        });
    }

    public function approve(VendaOperacaoPedido $pedido, User $approver): VendaOperacaoPedido
    {
        return DB::transaction(function () use ($pedido, $approver): VendaOperacaoPedido {
            $pedido = VendaOperacaoPedido::query()
                ->with(['vendasOperacao.produto.produtoMovimentacoes', 'vendasOperacao.produto.categoriaProduto'])
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if ($pedido->aprovado_em && in_array($pedido->status, [
                VendaStatus::Confirmada->value,
                VendaStatus::ParcialmenteDespachada->value,
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
            ], true)) {
                return $pedido->fresh([
                    'vendasOperacao.produto',
                    'vendasOperacao.produtoMovimentacao',
                    'aprovadoPor',
                    'cliente',
                    'user',
                ]);
            }

            if (! $pedido->isPendenteAprovacao()) {
                throw ValidationException::withMessages([
                    'status' => 'Somente vendas pendentes de aprovacao podem ser autorizadas.',
                ]);
            }

            $faltas = $this->estoqueService->faltasVenda($pedido, bloquear: true);

            if ($faltas !== []) {
                throw ValidationException::withMessages([
                    'estoque' => 'A venda continua sem saldo suficiente. Reponha o estoque antes de aprovar.',
                ]);
            }

            foreach ($pedido->vendasOperacao as $linha) {
                $linha->forceFill([
                    'desconto_aprovado_por' => $linha->desconto_requer_aprovacao ? $approver->id : $linha->desconto_aprovado_por,
                    'desconto_aprovado_em' => $linha->desconto_requer_aprovacao ? now() : $linha->desconto_aprovado_em,
                ])->save();
            }

            $pedido->forceFill([
                'aprovado_por' => $approver->id,
                'aprovado_em' => now(),
                'motivo_recusa' => null,
            ])->save();

            $pedido = $this->workflowService->confirmar(
                $pedido,
                $approver,
                "aprovar:venda:{$pedido->id}:v{$pedido->versao}",
            );

            $this->finalizeOpportunityAfterApproval($pedido);

            return $pedido->fresh([
                'vendasOperacao.produto',
                'vendasOperacao.produtoMovimentacao',
                'aprovadoPor',
                'cliente',
                'user',
            ]);
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

            $oportunidadeLiberadaId = $this->workflowService->desvincularOportunidadePendente($pedido);

            $pedido->forceFill([
                'status' => VendaOperacaoPedido::STATUS_RECUSADA,
                'aprovado_por' => $approver->id,
                'aprovado_em' => now(),
                'motivo_recusa' => $this->normalizeString($motivo),
            ])->save();

            $this->workflowService->registrarHistorico(
                $pedido,
                $approver,
                'recusada',
                VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO,
                VendaOperacaoPedido::STATUS_RECUSADA,
                $this->normalizeString($motivo),
                metadados: $oportunidadeLiberadaId
                    ? ['oportunidade_liberada_id' => $oportunidadeLiberadaId]
                    : [],
            );

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
                    'desconto_percentual' => 0,
                    'icms_aliquota' => 0,
                    'outros_impostos_aliquota' => 0,
                ];
            })
            ->values()
            ->all();

        $authUser = auth()->user();

        return [
            'cliente_id' => $origem->cliente_id,
            'data_venda' => now()->toDateString(),
            'vendedor_user_id' => $authUser?->id,
            'observacao' => null,
            'condicao_pagamento_snapshot' => $origem->condicao_pagamento_snapshot,
            'condicoes_comerciais' => $origem->condicoes_comerciais,
            'entrega_endereco' => $origem->entrega_endereco_snapshot,
            'quantidade_volumes' => null,
            'origem_pedido_id' => $origem->id,
            'itens' => $itens,
        ];
    }

    /** @return array<string, mixed> */
    public function buildEditPayload(VendaOperacaoPedido $pedido): array
    {
        $pedido->loadMissing('vendasOperacao');

        return [
            'cliente_id' => $pedido->cliente_id,
            'data_venda' => $pedido->data_venda?->toDateString(),
            'vendedor_user_id' => $pedido->user_id,
            'observacao' => $pedido->observacao,
            'condicao_pagamento_snapshot' => $pedido->condicao_pagamento_snapshot,
            'condicoes_comerciais' => $pedido->condicoes_comerciais,
            'entrega_endereco' => $pedido->entrega_endereco_snapshot,
            'quantidade_volumes' => $pedido->quantidade_volumes,
            'itens' => $pedido->vendasOperacao->map(fn (VendaOperacao $linha): array => [
                'produto_id' => $linha->produto_id,
                'quantidade' => (float) $linha->quantidade,
                'preco_unitario' => (float) $linha->preco_unitario,
                'desconto_percentual' => (float) $linha->desconto_percentual,
                'icms_aliquota' => (float) $linha->icms_aliquota,
                'outros_impostos_aliquota' => (float) $linha->outros_impostos_aliquota,
            ])->values()->all(),
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

        if ($user->hasAnyRole([
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
        ])) {
            return true;
        }

        return $user->getAllPermissions()->contains('name', PermissoesEnum::AprovarDesconto->value);
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

    protected function assertSemDependenciasDocumentais(VendaOperacaoPedido $pedido): void
    {
        $possuiItensDependentes = $pedido->vendasOperacao()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('lotes')
                    ->orWhereHas('fotos')
                    ->orWhereHas('romaneioItens');
            })
            ->exists();

        if ($possuiItensDependentes
            || $pedido->fotos()->exists()
            || $pedido->romaneioPedidos()->exists()) {
            throw ValidationException::withMessages([
                'itens' => 'Este pedido possui lotes, fotos ou historico de romaneio. Os itens nao podem ser substituidos sem perder a rastreabilidade.',
            ]);
        }
    }

    public function refreshPedidoTotals(VendaOperacaoPedido $pedido): VendaOperacaoPedido
    {
        $linhas = $pedido->vendasOperacao()->get();

        $pedido->forceFill([
            'itens_count' => $linhas->count(),
            'quantidade_total' => round((float) $linhas->sum('quantidade'), 4),
            'receita_bruta_total' => round((float) $linhas->sum('receita_bruta') + (float) $pedido->valor_frete_cobrado, 2),
            'receita_liquida_total' => round((float) $linhas->sum('receita_liquida') + (float) $pedido->valor_frete_cobrado, 2),
            'custo_total_snapshot' => round((float) $linhas->sum('custo_total_snapshot') + (float) $pedido->valor_frete_custo, 2),
            'lucro_bruto_total' => round((float) $linhas->sum('lucro_bruto') + (float) $pedido->valor_frete_cobrado - (float) $pedido->valor_frete_custo, 2),
            'lucro_apos_impostos_total' => round((float) $linhas->sum('lucro_apos_impostos') + (float) $pedido->valor_frete_cobrado - (float) $pedido->valor_frete_custo, 2),
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

        return VendaOperacao::query()->create([
            'user_id' => $user?->id,
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'produto_movimentacao_id' => null,
            'produto_codigo_snapshot' => $produto->codigo_interno,
            'produto_nome_snapshot' => $produto->nome,
            'produto_categoria_snapshot' => $produto->categoriaProduto?->nome,
            'unidade_snapshot' => $produto->unidade_medida,
            'peso_unitario_kg_snapshot' => $produto->peso_unitario_kg,
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
        $vendedor = $this->resolveVendedor($data['vendedor_user_id'] ?? null, $user);
        $quantidadeVolumes = filled($data['quantidade_volumes'] ?? null)
            ? (int) $data['quantidade_volumes']
            : null;

        if ($quantidadeVolumes !== null && $quantidadeVolumes < 1) {
            throw ValidationException::withMessages([
                'quantidade_volumes' => 'A quantidade de volumes deve ser maior que zero.',
            ]);
        }

        $enderecoCliente = $this->normalizeEndereco($cliente ? [
            'cep' => $cliente->cep,
            'logradouro' => $cliente->logradouro,
            'numero' => $cliente->numero,
            'complemento' => $cliente->complemento,
            'bairro' => $cliente->bairro,
            'cidade' => $cliente->cidade,
            'uf' => $cliente->uf,
        ] : null);
        $enderecoEntrega = $this->normalizeEndereco($data['entrega_endereco'] ?? null) ?: $enderecoCliente;

        return [
            'data_venda' => $data['data_venda'] ?? now()->toDateString(),
            'cliente_id' => $cliente?->id,
            'cliente_nome' => $cliente?->razao_social ?? $this->normalizeString($data['cliente_nome'] ?? null),
            'cliente_documento' => $cliente?->cnpj,
            'cliente_telefone' => $cliente?->telefone,
            'cliente_email' => $cliente?->email,
            'cliente_endereco' => $enderecoCliente,
            'entrega_endereco' => $enderecoEntrega,
            'condicao_pagamento' => $this->normalizeString($data['condicao_pagamento_snapshot'] ?? null),
            'condicoes_comerciais' => $this->normalizeString($data['condicoes_comerciais'] ?? null),
            'quantidade_volumes' => $quantidadeVolumes,
            'vendedor_user' => $vendedor,
            'vendedor_user_id' => $vendedor?->id,
            'vendedor_nome' => $vendedor?->name
                ?? $this->normalizeString($data['vendedor_nome'] ?? null)
                ?? $user?->name,
            'observacao' => $this->normalizeString($data['observacao'] ?? null),
            'origem_pedido_id' => filled($data['origem_pedido_id'] ?? null) ? (int) $data['origem_pedido_id'] : null,
        ];
    }

    protected function resolveVendedor(mixed $vendedorUserId, ?User $actor): ?User
    {
        $roleService = app(RoleService::class);

        if ($actor && ! $roleService->podeEscolherVendedor($actor)) {
            return $actor;
        }

        if (filled($vendedorUserId)) {
            $vendedor = User::query()->find((int) $vendedorUserId);

            if (! $vendedor) {
                throw ValidationException::withMessages([
                    'vendedor_user_id' => 'Selecione um vendedor valido.',
                ]);
            }

            if (! $vendedor->emailAprovado()) {
                throw ValidationException::withMessages([
                    'vendedor_user_id' => 'O vendedor selecionado nao possui acesso aprovado.',
                ]);
            }

            if ($actor
                && (int) $vendedor->id !== (int) $actor->id
                && ! $vendedor->hasRole(RolesEnum::Vendedor->value)) {
                throw ValidationException::withMessages([
                    'vendedor_user_id' => 'Selecione um usuario com o perfil Vendedor.',
                ]);
            }

            return $vendedor;
        }

        return $actor;
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

    protected function finalizeOpportunityAfterApproval(VendaOperacaoPedido $pedido): void
    {
        if (! $pedido->oportunidade_id || $pedido->status !== VendaStatus::Confirmada->value) {
            return;
        }

        $oportunidade = Oportunidade::query()
            ->lockForUpdate()
            ->find($pedido->oportunidade_id);

        if (! $oportunidade || $oportunidade->convertida_em) {
            return;
        }

        $etapaGanha = Etapa::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('tipo', EtapaTipo::Ganha->value)
                    ->orWhereIn('slug', ['ganho', 'win'])
                    ->orWhereRaw('lower(nome) in (?, ?)', ['ganho', 'win']);
            })
            ->orderByDesc('fechamento')
            ->orderBy('ordem')
            ->first();

        $oportunidade->markSaleAsConverted($pedido, $etapaGanha);
    }

    /**
     * @param  list<array<string, mixed>>  $faltas
     * @return array<string, mixed>|null
     */
    protected function buildApprovalReasons(bool $desconto, array $faltas): ?array
    {
        $motivos = [];

        if ($desconto) {
            $motivos['desconto'] = [
                'mensagem' => 'Há item vendido abaixo do preço mínimo.',
            ];
        }

        if ($faltas !== []) {
            $motivos['estoque'] = $faltas;
        }

        return $motivos === [] ? null : $motivos;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{cep:?string,logradouro:?string,numero:?string,complemento:?string,bairro:?string,cidade:?string,uf:?string}|null
     */
    protected function normalizeEndereco(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $endereco = [
            'cep' => $this->normalizeString($value['cep'] ?? null),
            'logradouro' => $this->normalizeString($value['logradouro'] ?? null),
            'numero' => $this->normalizeString($value['numero'] ?? null),
            'complemento' => $this->normalizeString($value['complemento'] ?? null),
            'bairro' => $this->normalizeString($value['bairro'] ?? null),
            'cidade' => $this->normalizeString($value['cidade'] ?? null),
            'uf' => $this->normalizeString($value['uf'] ?? null),
        ];

        return collect($endereco)->filter(fn (?string $campo): bool => filled($campo))->isEmpty()
            ? null
            : $endereco;
    }
}
