<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OportunidadeMovimentacao extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'oportunidade_movimentacoes';

    protected $fillable = [
        'oportunidade_id',
        'user_id',
        'etapa_origem_id',
        'etapa_destino_id',
        'motivo',
        'movido_em',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'user_id' => 'integer',
        'etapa_origem_id' => 'integer',
        'etapa_destino_id' => 'integer',
        'movido_em' => 'datetime',
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

    public function etapaOrigem(): BelongsTo
    {
        return $this->belongsTo(
            Etapa::class,
            'etapa_origem_id',
        );
    }

    public function etapaDestino(): BelongsTo
    {
        return $this->belongsTo(
            Etapa::class,
            'etapa_destino_id',
        );
    }
}
