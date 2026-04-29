<?php

namespace App\Models;

use App\Models\Categorias\CategoriaProduto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    protected $table = 'produtos';

    protected $fillable = [
        'codigo_interno',
        'categoria_produto_id',
        'nome',
        'marca',
        'descricao',
        'observacao',
        'unidade_medida',
        'status',
        'ncm',
        'estoque_minimo',
        'preco_tabela',
        'preco_minimo',
        'custo_base_formacao',
        'preco_sugerido',
        'ativo',
    ];

    protected $casts = [
        'categoria_produto_id' => 'integer',
        'estoque_minimo' => 'decimal:4',
        'preco_tabela' => 'decimal:2',
        'preco_minimo' => 'decimal:2',
        'custo_base_formacao' => 'decimal:4',
        'preco_sugerido' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $produto): void {
            if (blank($produto->codigo_interno)) {
                $produto->codigo_interno = static::generateCodigoInterno();
            }

            if (blank($produto->status) || $produto->status === 'em_registro') {
                $produto->status = 'ativo';
            }
        });

        static::saving(function (self $produto): void {
            if ($produto->status === 'em_registro') {
                $produto->status = 'ativo';
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            'ativo' => 'Ativo',
            'inativo' => 'Inativo',
            'descontinuado' => 'Descontinuado',
        ];
    }

    public function categoriaProduto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_produto_id');
    }

    public function oportunidadeProdutos(): HasMany
    {
        return $this->hasMany(
            OportunidadeProduto::class,
            'produto_id',
        );
    }

    public function produtoInsumos(): HasMany
    {
        return $this->hasMany(ProdutoInsumo::class, 'produto_id')->orderBy('ordem');
    }

    public function produtoComponentesCusto(): HasMany
    {
        return $this->hasMany(ProdutoComponenteCusto::class, 'produto_id')->orderBy('ordem');
    }

    public function produtoMovimentacoes(): HasMany
    {
        return $this->hasMany(ProdutoMovimentacao::class, 'produto_id')
            ->orderByDesc('realizado_em')
            ->orderByDesc('id');
    }

    public function estoqueAtual(): ?float
    {
        if (array_key_exists('estoque_atual', $this->attributes)) {
            return $this->attributes['estoque_atual'] !== null
                ? (float) $this->attributes['estoque_atual']
                : null;
        }

        if (array_key_exists('produto_movimentacoes_sum_impacto_estoque', $this->attributes)) {
            return $this->attributes['produto_movimentacoes_sum_impacto_estoque'] !== null
                ? (float) $this->attributes['produto_movimentacoes_sum_impacto_estoque']
                : null;
        }

        if (! $this->produtoMovimentacoes()->exists()) {
            return null;
        }

        return (float) $this->produtoMovimentacoes()->sum('impacto_estoque');
    }

    public function possuiHistoricoEstoque(): bool
    {
        if (array_key_exists('produto_movimentacoes_count', $this->attributes)) {
            return (int) $this->attributes['produto_movimentacoes_count'] > 0;
        }

        return $this->produtoMovimentacoes()->exists();
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

    protected static function generateCodigoInterno(): string
    {
        $ultimoNumero = static::query()
            ->where('codigo_interno', 'like', 'PRD-UBT-%')
            ->pluck('codigo_interno')
            ->map(function (string $codigo): int {
                if (preg_match('/PRD-UBT-(\d+)/', $codigo, $matches) !== 1) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return sprintf('PRD-UBT-%04d', ((int) $ultimoNumero) + 1);
    }
}
