<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoReserva extends Model
{
    public const STATUS_ATIVA = 'ativa';
    public const STATUS_CONSUMIDA = 'consumida';
    public const STATUS_LIBERADA = 'liberada';

    protected $table = 'produto_reservas';

    protected $fillable = [
        'venda_operacao_id',
        'produto_id',
        'quantidade',
        'quantidade_consumida',
        'status',
        'idempotency_key',
        'liberada_em',
        'consumida_em',
    ];

    protected $casts = [
        'venda_operacao_id' => 'integer',
        'produto_id' => 'integer',
        'quantidade' => 'decimal:4',
        'quantidade_consumida' => 'decimal:4',
        'liberada_em' => 'datetime',
        'consumida_em' => 'datetime',
    ];

    public function vendaOperacao(): BelongsTo
    {
        return $this->belongsTo(VendaOperacao::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function quantidadePendente(): float
    {
        return round(max(0, (float) $this->quantidade - (float) $this->quantidade_consumida), 4);
    }
}
