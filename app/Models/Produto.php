<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $table = 'produtos';

    protected $fillable = [
        'codigo_interno',
        'nome',
        'descricao',
        'unidade_medida',
        'preco_tabela',
        'ativo',
    ];

    protected $casts = [
        'preco_tabela' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function oportunidadeProdutos(): HasMany
    {
        return $this->hasMany(
            OportunidadeProduto::class,
            'produto_id',
        );
    }
}
