<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RomaneioPedido extends Model
{
    protected $table = 'romaneio_pedidos';

    protected $fillable = [
        'romaneio_id',
        'venda_operacao_pedido_id',
        'pedido_ativo_id',
        'cliente_id_snapshot',
        'pedido_codigo_snapshot',
        'pedido_data_snapshot',
        'cliente_nome_snapshot',
        'cliente_documento_snapshot',
        'cliente_telefone_snapshot',
        'cliente_email_snapshot',
        'cliente_endereco_snapshot',
        'entrega_endereco_snapshot',
        'vendedor_nome_snapshot',
        'condicao_pagamento_snapshot',
        'condicoes_comerciais_snapshot',
        'observacao_snapshot',
        'total_itens',
        'quantidade_total',
        'quantidade_volumes',
        'peso_total_kg',
        'valor_total',
    ];

    protected $casts = [
        'romaneio_id' => 'integer',
        'venda_operacao_pedido_id' => 'integer',
        'pedido_ativo_id' => 'integer',
        'cliente_id_snapshot' => 'integer',
        'pedido_data_snapshot' => 'date',
        'cliente_endereco_snapshot' => 'array',
        'entrega_endereco_snapshot' => 'array',
        'total_itens' => 'integer',
        'quantidade_total' => 'decimal:4',
        'quantidade_volumes' => 'integer',
        'peso_total_kg' => 'decimal:4',
        'valor_total' => 'decimal:2',
    ];

    public function romaneio(): BelongsTo
    {
        return $this->belongsTo(Romaneio::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            VendaOperacaoPedido::class,
            'venda_operacao_pedido_id',
        );
    }

    public function pedidoAtivo(): BelongsTo
    {
        return $this->belongsTo(
            VendaOperacaoPedido::class,
            'pedido_ativo_id',
        );
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RomaneioItem::class)->orderBy('id');
    }
}
