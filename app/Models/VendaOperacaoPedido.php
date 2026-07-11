<?php

namespace App\Models;

use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VendaOperacaoPedido extends Model
{
    public const STATUS_ATIVA = 'ativa';
    public const STATUS_PENDENTE_APROVACAO = 'pendente_aprovacao';
    public const STATUS_RECUSADA = 'recusada';
    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'venda_operacao_pedidos';

    protected $fillable = [
        'oportunidade_id',
        'origem_pedido_id',
        'cliente_id',
        'user_id',
        'aprovado_por',
        'aprovado_em',
        'codigo',
        'status',
        'data_venda',
        'ano_referencia',
        'mes_referencia',
        'cliente_nome_snapshot',
        'cliente_documento_snapshot',
        'vendedor_nome_snapshot',
        'itens_count',
        'quantidade_total',
        'receita_bruta_total',
        'receita_liquida_total',
        'custo_total_snapshot',
        'lucro_bruto_total',
        'lucro_apos_impostos_total',
        'observacao',
        'motivo_recusa',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'origem_pedido_id' => 'integer',
        'cliente_id' => 'integer',
        'user_id' => 'integer',
        'aprovado_por' => 'integer',
        'aprovado_em' => 'datetime',
        'data_venda' => 'date',
        'ano_referencia' => 'integer',
        'mes_referencia' => 'integer',
        'itens_count' => 'integer',
        'quantidade_total' => 'decimal:4',
        'receita_bruta_total' => 'decimal:2',
        'receita_liquida_total' => 'decimal:2',
        'custo_total_snapshot' => 'decimal:2',
        'lucro_bruto_total' => 'decimal:2',
        'lucro_apos_impostos_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $pedido): void {
            if (! $pedido->status) {
                $pedido->status = self::STATUS_ATIVA;
            }

            if (! $pedido->data_venda) {
                return;
            }

            $data = $pedido->data_venda instanceof Carbon
                ? $pedido->data_venda
                : Carbon::parse($pedido->data_venda);

            $pedido->ano_referencia = (int) $data->year;
            $pedido->mes_referencia = (int) $data->month;
        });
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ATIVA => 'Ativa',
            self::STATUS_PENDENTE_APROVACAO => 'Pendente de aprovacao',
            self::STATUS_RECUSADA => 'Recusada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function isPendenteAprovacao(): bool
    {
        return $this->status === self::STATUS_PENDENTE_APROVACAO;
    }

    public function isAtiva(): bool
    {
        return $this->status === self::STATUS_ATIVA;
    }

    public function assignCodigo(): void
    {
        if (filled($this->codigo) || ! $this->exists) {
            return;
        }

        $this->forceFill([
            'codigo' => sprintf('VOP-%05d', $this->id),
        ])->saveQuietly();
    }

    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(Oportunidade::class, 'oportunidade_id');
    }

    public function origemPedido(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origem_pedido_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function vendasOperacao(): HasMany
    {
        return $this->hasMany(VendaOperacao::class, 'venda_operacao_pedido_id')
            ->orderBy('id');
    }
}
