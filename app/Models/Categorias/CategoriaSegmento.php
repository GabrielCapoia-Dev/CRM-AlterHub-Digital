<?php

namespace App\Models\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Clientes\Cliente;

class CategoriaSegmento extends Model
{
    protected $table = 'categorias_segmentos';

    protected $fillable = [
        'nome',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(
            Cliente::class,
            'id_categoria_segmento'
        );
    }
}