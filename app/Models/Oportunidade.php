<?php

namespace App\Models;

use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Oportunidade extends Model
{
    public const TEMPERATURA_OPTIONS = [
        'hot' => 'Quente',
        'warm' => 'Morno',
        'cool' => 'Frio',
    ];

    protected $table = 'oportunidades';

    protected $fillable = [
        'titulo',
        'cliente_id',
        'etapa_id',
        'user_id',
        'temperatura',
        'valor_estimado',
        'motivo_fechamento',
        'notas',
    ];

    protected $casts = [
        'cliente_id' => 'integer',
        'etapa_id' => 'integer',
        'user_id' => 'integer',
        'valor_estimado' => 'decimal:2',
    ];

    protected ?array $etapaTransition = null;

    protected static function booted(): void
    {
        static::saving(function (self $oportunidade): void {
            $etapaId = $oportunidade->etapa_id;
            $isFechamento = $oportunidade->etapaEhDeFechamento($etapaId);
            $motivo = trim((string) ($oportunidade->motivo_fechamento ?? ''));

            if ($isFechamento && ($motivo === '')) {
                throw ValidationException::withMessages([
                    'motivo_fechamento' => 'O motivo de fechamento é obrigatório para etapas de encerramento.',
                ]);
            }

            if (! $isFechamento) {
                $oportunidade->motivo_fechamento = null;
            }
        });

        static::updating(function (self $oportunidade): void {
            $oportunidade->etapaTransition = null;

            if (! $oportunidade->isDirty('etapa_id')) {
                return;
            }

            $oportunidade->etapaTransition = [
                'etapa_origem_id' => (int) $oportunidade->getOriginal('etapa_id'),
                'etapa_destino_id' => (int) $oportunidade->etapa_id,
                'motivo' => $oportunidade->motivo_fechamento,
                'user_id' => Auth::id() ?: $oportunidade->user_id,
            ];
        });

        static::updated(function (self $oportunidade): void {
            if (! $oportunidade->etapaTransition) {
                return;
            }

            OportunidadeMovimentacao::create([
                'oportunidade_id' => $oportunidade->id,
                'user_id' => $oportunidade->etapaTransition['user_id'],
                'etapa_origem_id' => $oportunidade->etapaTransition['etapa_origem_id'],
                'etapa_destino_id' => $oportunidade->etapaTransition['etapa_destino_id'],
                'motivo' => $oportunidade->etapaTransition['motivo'],
                'movido_em' => now(),
            ]);

            $oportunidade->etapaTransition = null;
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Cliente::class,
            'cliente_id',
        );
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(
            Etapa::class,
            'etapa_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    public function oportunidadeProdutos(): HasMany
    {
        return $this->hasMany(
            OportunidadeProduto::class,
            'oportunidade_id',
        );
    }

    public function oportunidadeInteracoes(): HasMany
    {
        return $this->hasMany(
            OportunidadeInteracao::class,
            'oportunidade_id',
        );
    }

    public function oportunidadeTarefas(): HasMany
    {
        return $this->hasMany(
            OportunidadeTarefa::class,
            'oportunidade_id',
        );
    }

    public function oportunidadeMovimentacoes(): HasMany
    {
        return $this->hasMany(
            OportunidadeMovimentacao::class,
            'oportunidade_id',
        );
    }

    public static function temperaturaOptions(): array
    {
        return self::TEMPERATURA_OPTIONS;
    }

    protected function etapaEhDeFechamento(?int $etapaId): bool
    {
        if (! $etapaId) {
            return false;
        }

        return (bool) Etapa::query()
            ->whereKey($etapaId)
            ->value('fechamento');
    }
}
