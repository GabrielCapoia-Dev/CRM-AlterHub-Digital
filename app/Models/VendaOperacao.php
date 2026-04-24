<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class VendaOperacao extends Model
{
    protected $table = 'vendas_operacao';

    protected $fillable = [
        'user_id',
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
        'receita_bruta',
        'custo_unitario_snapshot',
        'custo_total_snapshot',
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
        'produto_movimentacao_id' => 'integer',
        'data_venda' => 'date',
        'ano_referencia' => 'integer',
        'mes_referencia' => 'integer',
        'quantidade' => 'decimal:4',
        'preco_unitario' => 'decimal:4',
        'receita_bruta' => 'decimal:2',
        'custo_unitario_snapshot' => 'decimal:4',
        'custo_total_snapshot' => 'decimal:2',
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

    public function produtoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoMovimentacao::class, 'produto_movimentacao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
