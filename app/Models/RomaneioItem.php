<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RomaneioItem extends Model
{
    protected $table = 'romaneio_itens';

    protected $fillable = [
        'romaneio_id',
        'romaneio_pedido_id',
        'venda_operacao_id',
        'produto_id',
        'produto_codigo_snapshot',
        'produto_nome_snapshot',
        'unidade_snapshot',
        'quantidade',
        'peso_unitario_kg',
        'peso_total_kg',
        'preco_unitario',
        'subtotal',
        'lotes_snapshot',
        'observacao_snapshot',
    ];

    protected $casts = [
        'romaneio_id' => 'integer',
        'romaneio_pedido_id' => 'integer',
        'venda_operacao_id' => 'integer',
        'produto_id' => 'integer',
        'quantidade' => 'decimal:4',
        'peso_unitario_kg' => 'decimal:4',
        'peso_total_kg' => 'decimal:4',
        'preco_unitario' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'lotes_snapshot' => 'array',
    ];

    public function romaneio(): BelongsTo
    {
        return $this->belongsTo(Romaneio::class);
    }

    public function romaneioPedido(): BelongsTo
    {
        return $this->belongsTo(RomaneioPedido::class);
    }

    public function vendaOperacao(): BelongsTo
    {
        return $this->belongsTo(VendaOperacao::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
