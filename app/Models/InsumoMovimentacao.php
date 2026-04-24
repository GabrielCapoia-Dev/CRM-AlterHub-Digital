<?php

namespace App\Models;

use App\Models\Acesso\User;
use App\Models\Produtos\Insumo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsumoMovimentacao extends Model
{
    protected $table = 'insumo_movimentacoes';

    protected $fillable = [
        'insumo_id',
        'user_id',
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
        'lote',
        'responsavel_nome',
        'valor_unitario',
        'valor_total',
        'observacao',
        'observacao_interna',
        'realizado_em',
    ];

    protected $casts = [
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
            'transferencia' => 'Transferencia',
            'ajuste' => 'Ajuste',
            'consumo_interno' => 'Consumo interno',
            'perda' => 'Perda',
        ];
    }

    public static function tipoColors(): array
    {
        return [
            'entrada' => 'success',
            'saida' => 'warning',
            'transferencia' => 'info',
            'ajuste' => 'gray',
            'consumo_interno' => 'danger',
            'perda' => 'danger',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
