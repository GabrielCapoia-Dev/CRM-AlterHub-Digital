<?php

namespace App\Models;

use App\Enum\EtapaTipo;
use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use Illuminate\Database\Eloquent\Builder;
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
        'venda_operacao_pedido_id',
        'convertida_em',
        'temperatura',
        'valor_estimado',
        'motivo_fechamento',
        'notas',
    ];

    protected $casts = [
        'cliente_id' => 'integer',
        'etapa_id' => 'integer',
        'user_id' => 'integer',
        'venda_operacao_pedido_id' => 'integer',
        'convertida_em' => 'datetime',
        'valor_estimado' => 'decimal:2',
    ];

    protected ?array $etapaTransition = null;

    protected bool $finalizingSaleConversion = false;

    protected static function booted(): void
    {
        static::saving(function (self $oportunidade): void {
            if ($oportunidade->exists && $oportunidade->wasCommerciallyFrozen() && ! $oportunidade->finalizingSaleConversion) {
                $camposComerciais = [
                    'titulo', 'cliente_id', 'etapa_id', 'user_id', 'temperatura',
                    'valor_estimado', 'motivo_fechamento', 'notas',
                ];

                if ($oportunidade->isDirty($camposComerciais)) {
                    throw ValidationException::withMessages([
                        'oportunidade' => 'Oportunidade convertida: dados comerciais e itens ficam congelados.',
                    ]);
                }
            }

            if ($oportunidade->exists
                && $oportunidade->originalStageWasLost()
                && ! $oportunidade->isDirty('etapa_id')
                && $oportunidade->isDirty(['titulo', 'cliente_id', 'user_id', 'temperatura', 'valor_estimado', 'notas'])) {
                throw ValidationException::withMessages([
                    'oportunidade' => 'Reabra a oportunidade perdida antes de editar os dados comerciais.',
                ]);
            }

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

    public function markSaleAsConverted(VendaOperacaoPedido $pedido, ?Etapa $wonStage): void
    {
        if ($this->convertida_em && $this->venda_operacao_pedido_id === $pedido->id) {
            return;
        }

        $payload = [
            'venda_operacao_pedido_id' => $pedido->id,
            'convertida_em' => now(),
        ];

        if ($wonStage) {
            $payload['etapa_id'] = $wonStage->id;
            $payload['motivo_fechamento'] = $wonStage->fechamento ? 'Venda confirmada' : null;
        }

        $this->finalizingSaleConversion = true;

        try {
            $this->forceFill($payload)->save();
        } finally {
            $this->finalizingSaleConversion = false;
        }
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

    public function vendaOperacaoPedido(): BelongsTo
    {
        return $this->belongsTo(
            VendaOperacaoPedido::class,
            'venda_operacao_pedido_id',
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

    public function calcularValorEstimado(): float
    {
        $produtos = $this->relationLoaded('oportunidadeProdutos')
            ? $this->oportunidadeProdutos
            : $this->oportunidadeProdutos()->with('produto')->get();

        return round((float) $produtos->sum(
            fn (OportunidadeProduto $produto): float => $produto->subtotalEstimado()
        ), 2);
    }

    public function recalcularValorEstimado(): void
    {
        $valor = $this->calcularValorEstimado();

        $this->forceFill([
            'valor_estimado' => $valor > 0 ? $valor : null,
        ])->saveQuietly();
    }

    public static function temperaturaOptions(): array
    {
        return self::TEMPERATURA_OPTIONS;
    }

    public function scopeVisiveisPara(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasAnyRole([
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
        ])
            || $user->hasPermissionTo(PermissoesEnum::AprovarDesconto->value)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function isConverted(): bool
    {
        return filled($this->venda_operacao_pedido_id) || filled($this->convertida_em);
    }

    public function isLost(): bool
    {
        if ($this->relationLoaded('etapa')) {
            return $this->etapa?->tipo === EtapaTipo::Perdida;
        }

        return Etapa::query()->whereKey($this->etapa_id)->value('tipo') === EtapaTipo::Perdida->value;
    }

    public function isOpen(): bool
    {
        if ($this->relationLoaded('etapa')) {
            return $this->etapa?->tipo === EtapaTipo::Aberta;
        }

        return Etapa::query()->whereKey($this->etapa_id)->value('tipo') === EtapaTipo::Aberta->value;
    }

    public function canEditCommercially(): bool
    {
        return ! $this->isConverted() && ! $this->isLost();
    }

    protected function wasCommerciallyFrozen(): bool
    {
        return filled($this->getOriginal('venda_operacao_pedido_id'))
            || filled($this->getOriginal('convertida_em'));
    }

    protected function originalStageWasLost(): bool
    {
        $etapaId = (int) $this->getOriginal('etapa_id');

        return $etapaId > 0
            && Etapa::query()->whereKey($etapaId)->value('tipo') === EtapaTipo::Perdida->value;
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
