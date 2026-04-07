<?php

namespace App\Models\Categorias;

use App\Models\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProduto extends Model
{
    protected $table = 'categorias_produto';

    protected $fillable = [
        'nome',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria_produto_id');
    }
}
