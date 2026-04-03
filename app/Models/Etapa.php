<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Etapa extends Model
{
    protected $table = 'etapas';

    protected $fillable = [
        'nome',
        'slug',
        'ordem',
        'cor',
        'fechamento',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'fechamento' => 'boolean',
    ];

    public function oportunidades(): HasMany
    {
        return $this->hasMany(
            Oportunidade::class,
            'etapa_id',
        );
    }
}
