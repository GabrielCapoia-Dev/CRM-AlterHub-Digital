<?php

namespace App\Models\Produtos;

use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Empresas\Fornecedor;
use App\Models\ProdutoInsumo;
use App\Models\Status\StatusInsumo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insumo extends Model
{
    protected $table = 'insumos';

    protected $fillable = [
        'codigo_interno',
        'fornecedor_id',
        'nome',
        'descricao',
        'tipo_insumo_id',
        'tipo_armazenamento_id',
        'tipo_unidade_medida_id',
        'status_insumo_id',
        'origem',
        'moeda_origem',
        'ncm',
        'custo_referencia',
        'custo_moeda_origem',
        'taxa_cambio',
        'valor_convertido_brl',
        'custo_nacionalizado',
        'estoque_minimo',
        'observacao',
    ];

    protected $casts = [
        'tipo_insumo_id' => 'integer',
        'tipo_armazenamento_id' => 'integer',
        'tipo_unidade_medida_id' => 'integer',
        'status_insumo_id' => 'integer',
        'fornecedor_id' => 'string',
        'custo_referencia' => 'decimal:4',
        'custo_moeda_origem' => 'decimal:4',
        'taxa_cambio' => 'decimal:6',
        'valor_convertido_brl' => 'decimal:4',
        'custo_nacionalizado' => 'decimal:4',
        'estoque_minimo' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $insumo): void {
            if (blank($insumo->codigo_interno)) {
                $insumo->codigo_interno = static::generateCodigoInterno();
            }
        });
    }

    public static function origemOptions(): array
    {
        return [
            'nacional' => 'Nacional',
            'importado' => 'Importado',
        ];
    }

    public static function moedaOptions(): array
    {
        return [
            'USD' => 'USD',
            'EUR' => 'EUR',
        ];
    }

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

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id', 'uuid');
    }

    public function insumoFatoresCusto(): HasMany
    {
        return $this->hasMany(InsumoFatorCusto::class, 'insumo_id')->orderBy('ordem');
    }

    public function produtoInsumos(): HasMany
    {
        return $this->hasMany(ProdutoInsumo::class, 'insumo_id');
    }

    protected static function generateCodigoInterno(): string
    {
        $ultimoNumero = static::query()
            ->where('codigo_interno', 'like', 'INS-UBT-%')
            ->pluck('codigo_interno')
            ->map(function (string $codigo): int {
                if (preg_match('/INS-UBT-(\d+)/', $codigo, $matches) !== 1) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return sprintf('INS-UBT-%04d', ((int) $ultimoNumero) + 1);
    }
}
