<?php

namespace App\Models;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class VendaOperacao extends Model
{
    protected $table = 'vendas_operacao';

    protected $fillable = [
        'user_id',
        'venda_operacao_pedido_id',
        'produto_id',
        'produto_movimentacao_id',
        'produto_codigo_snapshot',
        'produto_nome_snapshot',
        'produto_categoria_snapshot',
        'unidade_snapshot',
        'data_venda',
        'ano_referencia',
        'mes_referencia',
        'quantidade',
        'preco_unitario',
        'preco_tabela_snapshot',
        'preco_minimo_snapshot',
        'desconto_percentual',
        'desconto_requer_aprovacao',
        'desconto_aprovado_por',
        'desconto_aprovado_em',
        'receita_bruta',
        'custo_unitario_snapshot',
        'custo_total_snapshot',
        'regra_tributaria_id',
        'regra_tributaria_versao_snapshot',
        'uf_destino_snapshot',
        'ncm_snapshot',
        'icms_aliquota',
        'icms_valor',
        'outros_impostos_aliquota',
        'outros_impostos_valor',
        'receita_liquida',
        'lucro_bruto',
        'lucro_apos_impostos',
        'cliente_nome',
        'vendedor_nome',
        'observacao',
    ];

    protected $casts = [
        'produto_id' => 'integer',
        'venda_operacao_pedido_id' => 'integer',
        'produto_movimentacao_id' => 'integer',
        'data_venda' => 'date',
        'ano_referencia' => 'integer',
        'mes_referencia' => 'integer',
        'quantidade' => 'decimal:4',
        'preco_unitario' => 'decimal:4',
        'preco_tabela_snapshot' => 'decimal:2',
        'preco_minimo_snapshot' => 'decimal:2',
        'desconto_percentual' => 'decimal:2',
        'desconto_requer_aprovacao' => 'boolean',
        'desconto_aprovado_por' => 'integer',
        'desconto_aprovado_em' => 'datetime',
        'receita_bruta' => 'decimal:2',
        'custo_unitario_snapshot' => 'decimal:4',
        'custo_total_snapshot' => 'decimal:2',
        'regra_tributaria_id' => 'integer',
        'regra_tributaria_versao_snapshot' => 'integer',
        'icms_aliquota' => 'decimal:2',
        'icms_valor' => 'decimal:2',
        'outros_impostos_aliquota' => 'decimal:2',
        'outros_impostos_valor' => 'decimal:2',
        'receita_liquida' => 'decimal:2',
        'lucro_bruto' => 'decimal:2',
        'lucro_apos_impostos' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $venda): void {
            if (! $venda->data_venda) {
                return;
            }

            $data = $venda->data_venda instanceof Carbon
                ? $venda->data_venda
                : Carbon::parse($venda->data_venda);

            $venda->ano_referencia = (int) $data->year;
            $venda->mes_referencia = (int) $data->month;
        });
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function vendaOperacaoPedido(): BelongsTo
    {
        return $this->belongsTo(VendaOperacaoPedido::class, 'venda_operacao_pedido_id');
    }

    public function produtoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoMovimentacao::class, 'produto_movimentacao_id');
    }

    public function regraTributaria(): BelongsTo
    {
        return $this->belongsTo(RegraTributaria::class, 'regra_tributaria_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function descontoAprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desconto_aprovado_por');
    }

    public function reserva(): HasOne
    {
        return $this->hasOne(ProdutoReserva::class, 'venda_operacao_id');
    }

    public function scopeEfetivadas(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('venda_operacao_pedido_id')
                ->orWhereHas('vendaOperacaoPedido', fn (Builder $pedido) => $pedido->whereIn('status', [
                    VendaStatus::Confirmada->value,
                    VendaStatus::ParcialmenteDespachada->value,
                    VendaStatus::Despachada->value,
                    VendaStatus::Concluida->value,
                    VendaStatus::DevolvidaParcial->value,
                    VendaStatus::Devolvida->value,
                ]));
        });
    }
}
