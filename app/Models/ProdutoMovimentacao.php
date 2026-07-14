<?php

namespace App\Models;

use App\Models\Acesso\User;
use App\Models\Empresas\Fornecedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ProdutoMovimentacao extends Model
{
    protected $table = 'produto_movimentacoes';

    protected $fillable = [
        'produto_id',
        'user_id',
        'fornecedor_id',
        'tipo',
        'origem_tipo',
        'origem_id',
        'idempotency_key',
        'estorno_de_id',
        'estornado_por',
        'estornada_em',
        'quantidade',
        'impacto_estoque',
        'saldo_anterior',
        'saldo_atual',
        'unidade',
        'documento_referencia',
        'motivo',
        'origem_destino',
        'destino',
        'responsavel_nome',
        'valor_unitario',
        'valor_total',
        'observacao',
        'observacao_interna',
        'realizado_em',
    ];

    protected $casts = [
        'fornecedor_id' => 'string',
        'origem_id' => 'integer',
        'estorno_de_id' => 'integer',
        'estornado_por' => 'integer',
        'estornada_em' => 'datetime',
        'quantidade' => 'decimal:4',
        'impacto_estoque' => 'decimal:4',
        'saldo_anterior' => 'decimal:4',
        'saldo_atual' => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total' => 'decimal:4',
        'realizado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $movimentacao): void {
            $allowedFields = ['estornado_por', 'estornada_em', 'updated_at'];
            $changedFields = array_keys($movimentacao->getDirty());

            if (array_diff($changedFields, $allowedFields) !== []) {
                throw new LogicException('Movimentacoes de estoque sao imutaveis; registre um estorno.');
            }
        });

        static::deleting(fn () => throw new LogicException('Movimentacoes de estoque nao podem ser excluidas; registre um estorno.'));
    }

    public static function tipoOptions(): array
    {
        return [
            'entrada' => 'Entrada',
            'saida' => 'Saida',
            'consumo_interno' => 'Consumo interno',
            'perda' => 'Perda',
        ];
    }

    public static function tipoColors(): array
    {
        return [
            'entrada' => 'success',
            'saida' => 'warning',
            'consumo_interno' => 'danger',
            'perda' => 'danger',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(
            Fornecedor::class,
            'fornecedor_id',
            'uuid',
        );
    }

    public function estornoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorno_de_id');
    }

    public function estorno(): HasOne
    {
        return $this->hasOne(self::class, 'estorno_de_id');
    }

    public function estornadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estornado_por');
    }
}
