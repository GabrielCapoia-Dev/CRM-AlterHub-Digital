<?php

namespace App\Models;

use App\Models\Acesso\User;
use App\Models\Empresas\Fornecedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoMovimentacao extends Model
{
    protected $table = 'produto_movimentacoes';

    protected $fillable = [
        'produto_id',
        'user_id',
        'fornecedor_id',
        'tipo',
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
        'quantidade' => 'decimal:4',
        'impacto_estoque' => 'decimal:4',
        'saldo_anterior' => 'decimal:4',
        'saldo_atual' => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total' => 'decimal:4',
        'realizado_em' => 'datetime',
    ];

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
}
