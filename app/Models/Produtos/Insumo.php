<?php

namespace App\Models\Produtos;

use App\Models\Status\StatusInsumo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoUnidadeMedida;


class Insumo extends Model
{
    protected $table = 'insumos';

    protected $fillable = [
        'codigo_interno',
        'nome',
        'descricao',

        'tipo_insumo_id',
        'tipo_armazenamento_id',
        'tipo_unidade_medida_id',
        'status_insumo_id',

        'ncm',
        'custo_referencia',
        'estoque_minimo',

        'observacao',
    ];

    protected $casts = [
        'tipo_insumo_id'          => 'integer',
        'tipo_armazenamento_id'   => 'integer',
        'tipo_unidade_medida_id'  => 'integer',
        'status_insumo_id'        => 'integer',
        'custo_referencia'        => 'decimal:4',
        'estoque_minimo'          => 'decimal:4',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function tipoInsumo(): BelongsTo
    {
        return $this->belongsTo(
            TipoInsumo::class,
            'tipo_insumo_id'
        );
    }

    public function tipoArmazenamento(): BelongsTo
    {
        return $this->belongsTo(
            TipoArmazenamento::class,
            'tipo_armazenamento_id'
        );
    }

    public function tipoUnidadeMedida(): BelongsTo
    {
        return $this->belongsTo(
            TipoUnidadeMedida::class,
            'tipo_unidade_medida_id'
        );
    }

    public function statusInsumo(): BelongsTo
    {
        return $this->belongsTo(
            StatusInsumo::class,
            'status_insumo_id'
        );
    }
}