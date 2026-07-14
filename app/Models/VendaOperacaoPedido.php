<?php

namespace App\Models;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VendaOperacaoPedido extends Model
{
    /** @deprecated Use VendaStatus::Confirmada. */
    public const STATUS_ATIVA = VendaStatus::Confirmada->value;
    public const STATUS_RASCUNHO = VendaStatus::Rascunho->value;
    public const STATUS_PENDENTE_APROVACAO = VendaStatus::PendenteAprovacao->value;
    public const STATUS_CONFIRMADA = VendaStatus::Confirmada->value;
    public const STATUS_PARCIALMENTE_DESPACHADA = VendaStatus::ParcialmenteDespachada->value;
    public const STATUS_DESPACHADA = VendaStatus::Despachada->value;
    public const STATUS_CONCLUIDA = VendaStatus::Concluida->value;
    public const STATUS_RECUSADA = VendaStatus::Recusada->value;
    public const STATUS_CANCELADA = VendaStatus::Cancelada->value;
    public const STATUS_DEVOLVIDA_PARCIAL = VendaStatus::DevolvidaParcial->value;
    public const STATUS_DEVOLVIDA = VendaStatus::Devolvida->value;

    protected $table = 'venda_operacao_pedidos';

    protected $fillable = [
        'oportunidade_id',
        'origem_pedido_id',
        'cliente_id',
        'user_id',
        'aprovado_por',
        'aprovado_em',
        'codigo',
        'idempotency_key',
        'status',
        'versao',
        'data_venda',
        'confirmada_em',
        'cancelada_em',
        'concluida_em',
        'reaberta_em',
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
        'valor_frete_custo',
        'valor_frete_cobrado',
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
        'confirmada_em' => 'datetime',
        'cancelada_em' => 'datetime',
        'concluida_em' => 'datetime',
        'reaberta_em' => 'datetime',
        'versao' => 'integer',
        'ano_referencia' => 'integer',
        'mes_referencia' => 'integer',
        'itens_count' => 'integer',
        'quantidade_total' => 'decimal:4',
        'receita_bruta_total' => 'decimal:2',
        'receita_liquida_total' => 'decimal:2',
        'custo_total_snapshot' => 'decimal:2',
        'lucro_bruto_total' => 'decimal:2',
        'lucro_apos_impostos_total' => 'decimal:2',
        'valor_frete_custo' => 'decimal:2',
        'valor_frete_cobrado' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $pedido): void {
            if (! $pedido->status) {
                $pedido->status = VendaStatus::Rascunho->value;
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
        return VendaStatus::options();
    }

    public function isPendenteAprovacao(): bool
    {
        return $this->status === self::STATUS_PENDENTE_APROVACAO;
    }

    public function isAtiva(): bool
    {
        return $this->status === VendaStatus::Confirmada->value;
    }

    public function statusEnum(): VendaStatus
    {
        return VendaStatus::from($this->status);
    }

    public function canEditCommercially(): bool
    {
        return $this->statusEnum()->allowsCommercialEditing();
    }

    public function hasDispatchedItems(): bool
    {
        return $this->remessas()
            ->whereIn('status', [
                \App\Enum\RemessaStatus::Despachada->value,
                \App\Enum\RemessaStatus::Entregue->value,
            ])
            ->exists();
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

    public function remessas(): HasMany
    {
        return $this->hasMany(Remessa::class, 'venda_operacao_pedido_id')->orderBy('id');
    }

    public function devolucoes(): HasMany
    {
        return $this->hasMany(VendaDevolucao::class, 'venda_operacao_pedido_id')->orderBy('id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(VendaHistorico::class, 'venda_operacao_pedido_id')->orderByDesc('id');
    }
}
