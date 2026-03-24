<?php

namespace App\Models\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Empresas\Fornecedor;


class CategoriaFornecimento extends Model
{
    protected $table = 'categorias_fornecimento';

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
            'id_categoria_fornecimento'
        );
    }
}