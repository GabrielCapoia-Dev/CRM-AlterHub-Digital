<?php

namespace App\Models\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Produtos\Insumo;

class TipoUnidadeMedida extends Model
{
    protected $table = 'tipos_unidade_medida';

    protected $fillable = [
        'nome',
        'sigla',
    ];

    public function insumos(): HasMany
    {
        return $this->hasMany(
            Insumo::class,
            'tipo_unidade_medida_id'
        );
    }
}