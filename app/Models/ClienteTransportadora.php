<?php

namespace App\Models;

use App\Enum\ModalidadeEntrega;
use App\Models\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteTransportadora extends Model
{
    protected $table = 'cliente_transportadora';

    protected $fillable = [
        'cliente_id',
        'transportadora_id',
        'codigo_cliente_transportadora',
        'preferencial',
        'preferencial_cliente_id',
        'modalidade_entrega_padrao',
        'valor_frete_custo_padrao',
        'valor_frete_cobrado_padrao',
        'observacao',
    ];

    protected $casts = [
        'cliente_id' => 'integer',
        'transportadora_id' => 'integer',
        'preferencial' => 'boolean',
        'preferencial_cliente_id' => 'integer',
        'modalidade_entrega_padrao' => ModalidadeEntrega::class,
        'valor_frete_custo_padrao' => 'decimal:2',
        'valor_frete_cobrado_padrao' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $link->preferencial_cliente_id = $link->preferencial
                ? $link->cliente_id
                : null;

            if (! $link->preferencial || ! $link->cliente_id) {
                return;
            }

            static::query()
                ->where('cliente_id', $link->cliente_id)
                ->when($link->exists, fn ($query) => $query->where('id', '!=', $link->getKey()))
                ->update([
                    'preferencial' => false,
                    'preferencial_cliente_id' => null,
                ]);
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class, 'transportadora_id');
    }
}
