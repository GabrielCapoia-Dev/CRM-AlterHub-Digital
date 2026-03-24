<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected $fillable = [
        'nome',
    ];

    protected $casts = [
        'nome'       => 'string',
    ];

    public function fornecedores(): HasMany
    {
        return $this->hasMany(
            Fornecedor::class,
            'id_forma_pagamento'
        );
    }
}