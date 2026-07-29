<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RomaneioHistorico extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'romaneio_historicos';

    protected $fillable = [
        'romaneio_id',
        'user_id',
        'evento',
        'status_anterior',
        'status_novo',
        'justificativa',
        'metadados',
    ];

    protected $casts = [
        'romaneio_id' => 'integer',
        'user_id' => 'integer',
        'metadados' => 'array',
    ];

    public function romaneio(): BelongsTo
    {
        return $this->belongsTo(Romaneio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
