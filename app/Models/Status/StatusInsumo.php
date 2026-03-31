<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Produtos\Insumo;

class StatusInsumo extends Model
{
    protected $table = 'status_insumos';

    protected $fillable = [
        'nome',
    ];

    public function insumos(): HasMany
    {
        return $this->hasMany(
            Insumo::class,
            'status_insumo_id'
        );
    }
}