<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemessaItem extends Model
{
    protected $table = 'remessa_itens';

    protected $fillable = [
        'remessa_id',
        'venda_operacao_id',
        'produto_id',
        'produto_movimentacao_id',
        'quantidade',
        'quantidade_devolvida',
    ];

    protected $casts = [
        'remessa_id' => 'integer',
        'venda_operacao_id' => 'integer',
        'produto_id' => 'integer',
        'produto_movimentacao_id' => 'integer',
        'quantidade' => 'decimal:4',
        'quantidade_devolvida' => 'decimal:4',
    ];

    public function remessa(): BelongsTo
    {
        return $this->belongsTo(Remessa::class);
    }

    public function vendaOperacao(): BelongsTo
    {
        return $this->belongsTo(VendaOperacao::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function produtoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoMovimentacao::class);
    }

    public function devolucaoItens(): HasMany
    {
        return $this->hasMany(VendaDevolucaoItem::class);
    }
}
