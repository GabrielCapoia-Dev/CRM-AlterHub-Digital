<?php

namespace App\Models\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Produtos\Insumo;

class TipoArmazenamento extends Model
{
    protected $table = 'tipos_armazenamento';

    protected $fillable = [
        'nome',
    ];

    public function insumos(): HasMany
    {
        return $this->hasMany(
            Insumo::class,
            'tipo_armazenamento_id'
        );
    }
}