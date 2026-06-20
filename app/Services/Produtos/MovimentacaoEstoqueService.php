<?php

namespace App\Services\Produtos;

use App\Models\Acesso\User;
use App\Models\InsumoMovimentacao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Produtos\Insumo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimentacaoEstoqueService
{
    protected const OUTBOUND_TYPES = [
        'saida',
        'consumo_interno',
        'perda',
    ];

    public function prepareForPersistence(array $data, float $saldoAnterior): array
    {
        $normalized = $this->normalizePayload($data);

        $this->validate($normalized, $saldoAnterior);

        $impactoEstoque = $this->resolveImpactoEstoque(
            tipo: $normalized['tipo'],
            quantidade: $normalized['quantidade'],
        );

        $saldoAtual = round($saldoAnterior + $impactoEstoque, 4);

        $valorTotal = $normalized['valor_total'];

        if ($valorTotal === null && $normalized['valor_unitario'] !== null) {
            $valorTotal = round($normalized['quantidade'] * $normalized['valor_unitario'], 4);
        }

        return [
            ...$normalized,
            'impacto_estoque' => $impactoEstoque,
            'saldo_anterior' => round($saldoAnterior, 4),
            'saldo_atual' => $saldoAtual,
            'valor_total' => $valorTotal,
        ];
    }

    public function createForInsumo(array $data, ?User $user = null): InsumoMovimentacao
    {
        return DB::transaction(function () use ($data, $user): InsumoMovimentacao {
            $insumo = Insumo::query()
                ->with(['tipoUnidadeMedida', 'insumoFatoresCusto'])
                ->findOrFail($data['insumo_id']);

            $prepared = $this->prepareForPersistence([
                ...$data,
                'unidade' => $data['unidade'] ?? $this->resolveInsumoUnidade($insumo),
                'valor_unitario' => $data['valor_unitario'] ?? $insumo->finalCostAmount(),
                'valor_total' => $data['valor_total'] ?? ($insumo->finalCostAmount() * ((float) ($data['quantidade'] ?? 0))),
            ], $insumo->estoqueAtual() ?? 0.0);

            $assignedUser = $this->resolveAssignedUser($prepared['user_id'] ?? null, $user);

            return InsumoMovimentacao::query()->create([
                ...Arr::except($prepared, ['insumo_id']),
                'insumo_id' => $insumo->id,
                'user_id' => $assignedUser?->id,
                'unidade' => $prepared['unidade'] ?? $this->resolveInsumoUnidade($insumo),
                'responsavel_nome' => $assignedUser?->name ?? $prepared['responsavel_nome'] ?? $user?->name,
            ]);
        });
    }

    public function createForProduto(array $data, ?User $user = null): ProdutoMovimentacao
    {
        return DB::transaction(function () use ($data, $user): ProdutoMovimentacao {
            $produto = Produto::query()
                ->lockForUpdate()
                ->findOrFail($data['produto_id']);

            $custoBase = $this->resolveProdutoCustoBase($produto);

            $prepared = $this->prepareForPersistence([
                ...$data,
                'unidade' => $data['unidade'] ?? $produto->unidade_medida,
                'valor_unitario' => $data['valor_unitario'] ?? $custoBase,
                'valor_total' => $data['valor_total'] ?? ($custoBase * ((float) ($data['quantidade'] ?? 0))),
            ], $produto->estoqueAtual() ?? 0.0);
            $assignedUser = $this->resolveAssignedUser($prepared['user_id'] ?? null, $user);

            return ProdutoMovimentacao::query()->create([
                ...Arr::except($prepared, ['produto_id']),
                'produto_id' => $produto->id,
                'user_id' => $assignedUser?->id,
                'unidade' => $prepared['unidade'] ?? $produto->unidade_medida,
                'responsavel_nome' => $assignedUser?->name ?? $prepared['responsavel_nome'] ?? $user?->name,
            ]);
        });
    }

    protected function validate(array $data, float $saldoAnterior): void
    {
        $messages = [];

        if (! array_key_exists($data['tipo'], InsumoMovimentacao::tipoOptions())) {
            $messages['tipo'] = 'Selecione um tipo de movimentacao valido.';
        }

        if (($data['quantidade'] ?? 0) <= 0) {
            $messages['quantidade'] = 'Informe uma quantidade maior que zero.';
        }

        if ($this->isOutboundType($data['tipo']) && $data['quantidade'] > $saldoAnterior) {
            $messages['quantidade'] = 'A quantidade informada ultrapassa o saldo atual disponivel.';
        }

        if (($data['user_id'] ?? null) !== null && User::query()->whereKey($data['user_id'])->doesntExist()) {
            $messages['user_id'] = 'Selecione um responsavel valido para esta movimentacao.';
        }

        if ($this->isOutboundType($data['tipo']) && blank($data['motivo'] ?? null)) {
            $messages['motivo'] = 'Informe o motivo desta movimentacao.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function normalizePayload(array $data): array
    {
        return [
            ...$data,
            'tipo' => $data['tipo'] ?? 'entrada',
            'user_id' => isset($data['user_id']) && $data['user_id'] !== '' ? (int) $data['user_id'] : null,
            'quantidade' => $this->toNullableFloat($data['quantidade'] ?? null) ?? 0.0,
            'unidade' => $this->trimOrNull($data['unidade'] ?? null),
            'documento_referencia' => $this->trimOrNull($data['documento_referencia'] ?? null),
            'motivo' => $this->trimOrNull($data['motivo'] ?? null),
            'origem_destino' => $this->trimOrNull($data['origem_destino'] ?? null),
            'destino' => $this->trimOrNull($data['destino'] ?? null),
            'lote' => $this->trimOrNull($data['lote'] ?? null),
            'responsavel_nome' => $this->trimOrNull($data['responsavel_nome'] ?? null),
            'valor_unitario' => $this->toNullableFloat($data['valor_unitario'] ?? null),
            'valor_total' => $this->toNullableFloat($data['valor_total'] ?? null),
            'observacao' => $this->trimOrNull($data['observacao'] ?? null),
            'observacao_interna' => $this->trimOrNull($data['observacao_interna'] ?? null),
            'realizado_em' => $data['realizado_em'] ?? now(),
        ];
    }

    protected function resolveImpactoEstoque(string $tipo, float $quantidade): float
    {
        return match ($tipo) {
            'entrada' => round($quantidade, 4),
            'saida', 'consumo_interno', 'perda' => round($quantidade * -1, 4),
            default => 0.0,
        };
    }

    protected function isOutboundType(string $tipo): bool
    {
        return in_array($tipo, self::OUTBOUND_TYPES, true);
    }

    protected function resolveInsumoUnidade(Insumo $insumo): ?string
    {
        return $insumo->tipoUnidadeMedida?->sigla
            ?: $insumo->tipoUnidadeMedida?->nome;
    }

    protected function resolveAssignedUser(?int $userId, ?User $fallbackUser): ?User
    {
        if ($userId) {
            return User::query()->find($userId);
        }

        return $fallbackUser;
    }

    protected function trimOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : trim((string) $value);
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    protected function resolveProdutoCustoBase(Produto $produto): float
    {
        return round((float) ($produto->custo_base_formacao ?? 0), 4);
    }
}
