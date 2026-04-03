<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OportunidadeTarefa extends Model
{
    public const STATUS_OPTIONS = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
    ];

    protected $table = 'oportunidade_tarefas';

    protected $fillable = [
        'oportunidade_id',
        'user_id',
        'titulo',
        'status',
        'data_prevista',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'user_id' => 'integer',
        'data_prevista' => 'date',
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

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }
}
