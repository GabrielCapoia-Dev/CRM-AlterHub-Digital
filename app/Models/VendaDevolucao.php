<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendaDevolucao extends Model
{
    protected $table = 'venda_devolucoes';

    protected $fillable = [
        'venda_operacao_pedido_id',
        'user_id',
        'codigo',
        'idempotency_key',
        'motivo',
        'recebida_em',
    ];

    protected $casts = [
        'venda_operacao_pedido_id' => 'integer',
        'user_id' => 'integer',
        'recebida_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $devolucao): void {
            if (blank($devolucao->codigo)) {
                $devolucao->forceFill(['codigo' => sprintf('DEV-%06d', $devolucao->id)])->saveQuietly();
            }
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(VendaOperacaoPedido::class, 'venda_operacao_pedido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaDevolucaoItem::class)->orderBy('id');
    }
}
