<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Romaneio extends Model
{
    public const STATUS_GERADO = 'gerado';

    public const STATUS_ATIVO = self::STATUS_GERADO;

    public const STATUS_DESPACHADO = 'despachado';

    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'romaneios';

    protected $fillable = [
        'user_id',
        'cancelado_por',
        'despachado_por',
        'codigo',
        'status',
        'gerado_em',
        'cancelado_em',
        'despachado_em',
        'observacao',
        'justificativa_cancelamento',
        'empresa_snapshot',
        'total_pedidos',
        'total_clientes',
        'total_itens',
        'quantidade_total',
        'quantidade_volumes_total',
        'peso_total_kg',
        'valor_total',
        'exibir_valores_comerciais',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'cancelado_por' => 'integer',
        'gerado_em' => 'datetime',
        'cancelado_em' => 'datetime',
        'despachado_por' => 'integer',
        'despachado_em' => 'datetime',
        'empresa_snapshot' => 'array',
        'total_pedidos' => 'integer',
        'total_clientes' => 'integer',
        'total_itens' => 'integer',
        'quantidade_total' => 'decimal:4',
        'quantidade_volumes_total' => 'integer',
        'peso_total_kg' => 'decimal:4',
        'valor_total' => 'decimal:2',
        'exibir_valores_comerciais' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $romaneio): void {
            $romaneio->status ??= self::STATUS_GERADO;
            $romaneio->gerado_em ??= now();
        });
    }

    public function assignCodigo(): void
    {
        if (filled($this->codigo) || ! $this->exists) {
            return;
        }

        $this->forceFill([
            'codigo' => sprintf('ROM-%06d', $this->id),
        ])->saveQuietly();
    }

    public function isAtivo(): bool
    {
        return $this->isGerado();
    }

    public function isGerado(): bool
    {
        return $this->status === self::STATUS_GERADO;
    }

    public function isDespachado(): bool
    {
        return $this->status === self::STATUS_DESPACHADO;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geradoPor(): BelongsTo
    {
        return $this->user();
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function despachadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachado_por');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(RomaneioPedido::class)->orderBy('id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RomaneioItem::class)->orderBy('id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(RomaneioHistorico::class)->orderByDesc('id');
    }
}
