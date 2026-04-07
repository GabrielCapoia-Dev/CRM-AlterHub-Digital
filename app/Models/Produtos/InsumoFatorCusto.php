<?php

namespace App\Models\Produtos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsumoFatorCusto extends Model
{
    protected $table = 'insumo_fatores_custo';

    protected $fillable = [
        'insumo_id',
        'nome',
        'tipo',
        'valor',
        'ordem',
    ];

    protected $casts = [
        'insumo_id' => 'integer',
        'valor' => 'decimal:4',
        'ordem' => 'integer',
    ];

    public static function tipoOptions(): array
    {
        return [
            'percentual' => 'Percentual sobre a base',
            'valor_fixo_brl' => 'Valor fixo em BRL',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
