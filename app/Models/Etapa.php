<?php

namespace App\Models;

use App\Enum\EtapaTipo;
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
        'tipo',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'fechamento' => 'boolean',
        'tipo' => EtapaTipo::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $etapa): void {
            if (! $etapa->tipo) {
                $slug = mb_strtolower((string) ($etapa->slug ?? $etapa->nome));
                $etapa->tipo = ! $etapa->fechamento
                    ? EtapaTipo::Aberta
                    : (str_contains($slug, 'perd') || str_contains($slug, 'lost')
                        ? EtapaTipo::Perdida
                        : EtapaTipo::Ganha);
            }

            $etapa->fechamento = $etapa->tipo !== EtapaTipo::Aberta;
        });
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(
            Oportunidade::class,
            'etapa_id',
        );
    }
}
