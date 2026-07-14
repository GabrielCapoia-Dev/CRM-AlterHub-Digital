<?php

namespace App\Models;

use App\Enum\StatusOrdemProducao;
use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdemProducao extends Model
{
    protected $table = 'ordens_producao';

    protected $fillable = [
        'codigo',
        'produto_id',
        'user_id',
        'produto_movimentacao_id',
        'status',
        'quantidade_planejada',
        'quantidade_produzida',
        'unidade_snapshot',
        'produto_codigo_snapshot',
        'produto_nome_snapshot',
        'custo_unitario_snapshot',
        'custo_total_planejado_snapshot',
        'prevista_para',
        'reservada_em',
        'iniciada_em',
        'concluida_em',
        'cancelada_em',
        'observacao',
    ];

    protected $casts = [
        'produto_id' => 'integer',
        'user_id' => 'integer',
        'produto_movimentacao_id' => 'integer',
        'status' => StatusOrdemProducao::class,
        'quantidade_planejada' => 'decimal:4',
        'quantidade_produzida' => 'decimal:4',
        'custo_unitario_snapshot' => 'decimal:4',
        'custo_total_planejado_snapshot' => 'decimal:4',
        'prevista_para' => 'date',
        'reservada_em' => 'datetime',
        'iniciada_em' => 'datetime',
        'concluida_em' => 'datetime',
        'cancelada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ordem): void {
            if (blank($ordem->codigo)) {
                $ordem->codigo = static::generateCodigo();
            }

            $ordem->status ??= StatusOrdemProducao::Planejada;
        });
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function produtoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoMovimentacao::class, 'produto_movimentacao_id');
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(OrdemProducaoInsumo::class, 'ordem_producao_id')->orderBy('ordem');
    }

    public function podeReservar(): bool
    {
        return $this->status === StatusOrdemProducao::Planejada;
    }

    public function podeLiberarReserva(): bool
    {
        return $this->status === StatusOrdemProducao::Reservada;
    }

    public function podeConsumir(): bool
    {
        return $this->status === StatusOrdemProducao::Reservada;
    }

    public function podeConcluir(): bool
    {
        return in_array($this->status, [
            StatusOrdemProducao::Reservada,
            StatusOrdemProducao::EmProducao,
        ], true);
    }

    protected static function generateCodigo(): string
    {
        $prefix = 'OP-'.now()->format('Y').'-';
        $lastNumber = static::query()
            ->where('codigo', 'like', $prefix.'%')
            ->pluck('codigo')
            ->map(function (string $codigo) use ($prefix): int {
                return str_starts_with($codigo, $prefix)
                    ? (int) substr($codigo, strlen($prefix))
                    : 0;
            })
            ->max();

        return sprintf('%s%05d', $prefix, ((int) $lastNumber) + 1);
    }
}
