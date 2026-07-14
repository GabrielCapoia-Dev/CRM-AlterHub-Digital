<?php

namespace App\Models;

use App\Models\Produtos\Insumo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemProducaoInsumo extends Model
{
    protected $table = 'ordens_producao_insumos';

    protected $fillable = [
        'ordem_producao_id',
        'produto_insumo_id',
        'insumo_id',
        'insumo_movimentacao_id',
        'ordem',
        'insumo_codigo_snapshot',
        'insumo_nome_snapshot',
        'unidade_snapshot',
        'quantidade_unitaria_snapshot',
        'quantidade_necessaria_snapshot',
        'quantidade_reservada',
        'quantidade_consumida',
        'custo_unitario_snapshot',
        'custo_total_snapshot',
    ];

    protected $casts = [
        'ordem_producao_id' => 'integer',
        'produto_insumo_id' => 'integer',
        'insumo_id' => 'integer',
        'insumo_movimentacao_id' => 'integer',
        'ordem' => 'integer',
        'quantidade_unitaria_snapshot' => 'decimal:4',
        'quantidade_necessaria_snapshot' => 'decimal:4',
        'quantidade_reservada' => 'decimal:4',
        'quantidade_consumida' => 'decimal:4',
        'custo_unitario_snapshot' => 'decimal:4',
        'custo_total_snapshot' => 'decimal:4',
    ];

    public function ordemProducao(): BelongsTo
    {
        return $this->belongsTo(OrdemProducao::class, 'ordem_producao_id');
    }

    public function produtoInsumo(): BelongsTo
    {
        return $this->belongsTo(ProdutoInsumo::class, 'produto_insumo_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function insumoMovimentacao(): BelongsTo
    {
        return $this->belongsTo(InsumoMovimentacao::class, 'insumo_movimentacao_id');
    }
}
