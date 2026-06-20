<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OportunidadeProduto extends Model
{
    protected $table = 'oportunidade_produtos';

    protected $fillable = [
        'oportunidade_id',
        'produto_id',
        'quantidade',
        'preco_negociado',
        'observacao',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'produto_id' => 'integer',
        'quantidade' => 'decimal:4',
        'preco_negociado' => 'decimal:2',
    ];

    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(
            Oportunidade::class,
            'oportunidade_id',
        );
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(
            Produto::class,
            'produto_id',
        );
    }
}
