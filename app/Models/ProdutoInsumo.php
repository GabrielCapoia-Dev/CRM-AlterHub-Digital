<?php

namespace App\Models;

use App\Models\Produtos\Insumo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoInsumo extends Model
{
    protected $table = 'produto_insumos';

    protected $fillable = [
        'produto_id',
        'insumo_id',
        'quantidade',
        'unidade_consumo',
        'ordem',
        'custo_unitario_snapshot',
        'custo_total_snapshot',
    ];

    protected $casts = [
        'produto_id' => 'integer',
        'insumo_id' => 'integer',
        'quantidade' => 'decimal:4',
        'ordem' => 'integer',
        'custo_unitario_snapshot' => 'decimal:4',
        'custo_total_snapshot' => 'decimal:4',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
