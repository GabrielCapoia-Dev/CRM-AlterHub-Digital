<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaDevolucaoItem extends Model
{
    protected $table = 'venda_devolucao_itens';

    protected $fillable = [
        'venda_devolucao_id',
        'remessa_item_id',
        'produto_movimentacao_id',
        'quantidade',
    ];

    protected $casts = [
        'venda_devolucao_id' => 'integer',
        'remessa_item_id' => 'integer',
        'produto_movimentacao_id' => 'integer',
        'quantidade' => 'decimal:4',
    ];

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(VendaDevolucao::class, 'venda_devolucao_id');
    }

    public function remessaItem(): BelongsTo
    {
        return $this->belongsTo(RemessaItem::class);
    }

    public function produtoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoMovimentacao::class);
    }
}
