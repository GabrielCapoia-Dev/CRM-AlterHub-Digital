<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoComponenteCusto extends Model
{
    protected $table = 'produto_componentes_custo';

    protected $fillable = [
        'produto_id',
        'nome',
        'categoria',
        'tipo',
        'valor',
        'obrigatorio',
        'is_margem',
        'ordem',
    ];

    protected $casts = [
        'produto_id' => 'integer',
        'valor' => 'decimal:4',
        'obrigatorio' => 'boolean',
        'is_margem' => 'boolean',
        'ordem' => 'integer',
    ];

    public static function categoriaOptions(): array
    {
        return [
            'impostos' => 'Impostos',
            'comerciais' => 'Comerciais',
            'custos_fixos' => 'Custos fixos',
            'personalizado' => 'Personalizado',
        ];
    }

    public static function tipoOptions(): array
    {
        return [
            'percentual_sobre_venda' => 'Percentual sobre a venda',
            'valor_fixo_brl' => 'Valor fixo em BRL',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
