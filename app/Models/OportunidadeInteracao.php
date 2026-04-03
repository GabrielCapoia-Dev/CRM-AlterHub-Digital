<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OportunidadeInteracao extends Model
{
    public const TIPO_OPTIONS = [
        'ligacao' => 'Ligação',
        'visita' => 'Visita',
        'email' => 'E-mail',
        'observacao' => 'Observação',
    ];

    protected $table = 'oportunidade_interacoes';

    protected $fillable = [
        'oportunidade_id',
        'user_id',
        'tipo',
        'nota',
        'ocorreu_em',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'user_id' => 'integer',
        'ocorreu_em' => 'datetime',
    ];

    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(
            Oportunidade::class,
            'oportunidade_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    public static function tipoOptions(): array
    {
        return self::TIPO_OPTIONS;
    }
}
