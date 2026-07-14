<?php

namespace App\Models\Produtos;

use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Empresas\Fornecedor;
use App\Models\InsumoMovimentacao;
use App\Models\OrdemProducaoInsumo;
use App\Models\ProdutoInsumo;
use App\Models\Status\StatusInsumo;
use App\Services\Produtos\InsumoCostCalculator;
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
        'estoque_fisico',
        'estoque_reservado',
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
        'estoque_fisico' => 'decimal:4',
        'estoque_reservado' => 'decimal:4',
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

    public function insumoMovimentacoes(): HasMany
    {
        return $this->hasMany(InsumoMovimentacao::class, 'insumo_id')
            ->orderByDesc('realizado_em')
            ->orderByDesc('id');
    }

    public function ordemProducaoInsumos(): HasMany
    {
        return $this->hasMany(OrdemProducaoInsumo::class, 'insumo_id');
    }

    public function quantidadeReservadaProducao(): float
    {
        return (float) $this->ordemProducaoInsumos()->sum('quantidade_reservada');
    }

    public function estoqueDisponivelParaProducao(): float
    {
        return $this->estoqueDisponivel();
    }

    public function estoqueAtual(): ?float
    {
        if (array_key_exists('estoque_fisico', $this->attributes)) {
            return (float) ($this->attributes['estoque_fisico'] ?? 0);
        }

        if (array_key_exists('estoque_atual', $this->attributes)) {
            return $this->attributes['estoque_atual'] !== null
                ? (float) $this->attributes['estoque_atual']
                : null;
        }

        if (array_key_exists('insumo_movimentacoes_sum_impacto_estoque', $this->attributes)) {
            return $this->attributes['insumo_movimentacoes_sum_impacto_estoque'] !== null
                ? (float) $this->attributes['insumo_movimentacoes_sum_impacto_estoque']
                : null;
        }

        if (! $this->insumoMovimentacoes()->exists()) {
            return null;
        }

        return (float) $this->insumoMovimentacoes()->sum('impacto_estoque');
    }

    public function estoqueDisponivel(): float
    {
        return round(
            (float) ($this->estoqueAtual() ?? 0) - (float) ($this->estoque_reservado ?? 0),
            4,
        );
    }

    public function possuiHistoricoEstoque(): bool
    {
        if (array_key_exists('insumo_movimentacoes_count', $this->attributes)) {
            return (int) $this->attributes['insumo_movimentacoes_count'] > 0;
        }

        return $this->insumoMovimentacoes()->exists();
    }

    public function estoqueEstaBaixo(): bool
    {
        $estoqueAtual = $this->estoqueAtual();
        $estoqueMinimo = $this->estoque_minimo !== null ? (float) $this->estoque_minimo : null;

        return $estoqueAtual !== null
            && $estoqueMinimo !== null
            && $estoqueMinimo > 0
            && $estoqueAtual <= $estoqueMinimo;
    }

    public function effectiveCostAmount(): float
    {
        return (float) $this->costCalculation()['valor_convertido_brl'];
    }

    public function finalCostAmount(): float
    {
        return (float) $this->costCalculation()['custo_nacionalizado'];
    }

    public function costCalculation(): array
    {
        return app(InsumoCostCalculator::class)->calculate(
            origem: $this->origem ?? 'nacional',
            custoUnitarioBrl: $this->custo_referencia !== null ? (float) $this->custo_referencia : null,
            custoMoedaOrigem: $this->custo_moeda_origem !== null ? (float) $this->custo_moeda_origem : null,
            taxaCambio: $this->taxa_cambio !== null ? (float) $this->taxa_cambio : null,
            fatores: $this->costFactorsPayload(),
        );
    }

    protected function costFactorsPayload(): array
    {
        $fatores = $this->relationLoaded('insumoFatoresCusto')
            ? $this->insumoFatoresCusto
            : $this->insumoFatoresCusto()->get();

        return $fatores
            ->map(fn (InsumoFatorCusto $fator): array => [
                'nome' => $fator->nome,
                'tipo' => $fator->tipo,
                'valor' => (float) $fator->valor,
                'ordem' => $fator->ordem,
            ])
            ->all();
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
