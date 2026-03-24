<?php

namespace App\Models\Empresas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrazoPagamento extends Model
{
    protected $table = 'prazos_pagamento';

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
            'id_prazo_pagamento'
        );
    }
}