<?php

namespace App\Models;

use App\Enum\RemessaStatus;
use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remessa extends Model
{
    protected $table = 'remessas';

    protected $fillable = [
        'venda_operacao_pedido_id',
        'transportadora_id',
        'user_id',
        'codigo',
        'status',
        'modalidade_entrega',
        'idempotency_key',
        'valor_frete_custo',
        'valor_frete_cobrado',
        'codigo_rastreio',
        'despachada_em',
        'entregue_em',
        'cancelada_em',
        'observacao',
    ];

    protected $casts = [
        'venda_operacao_pedido_id' => 'integer',
        'transportadora_id' => 'integer',
        'user_id' => 'integer',
        'status' => RemessaStatus::class,
        'valor_frete_custo' => 'decimal:2',
        'valor_frete_cobrado' => 'decimal:2',
        'despachada_em' => 'datetime',
        'entregue_em' => 'datetime',
        'cancelada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $remessa): void {
            if (blank($remessa->codigo)) {
                $remessa->forceFill(['codigo' => sprintf('REM-%06d', $remessa->id)])->saveQuietly();
            }
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(VendaOperacaoPedido::class, 'venda_operacao_pedido_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RemessaItem::class)->orderBy('id');
    }
}
