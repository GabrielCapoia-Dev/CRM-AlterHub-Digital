<?php

namespace App\Models;

use App\Enum\ProdutoClassificacao;
use App\Enum\ProdutoOrigem;
use App\Models\Categorias\CategoriaProduto;
use App\Models\Empresas\Fornecedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    protected $table = 'produtos';

    protected $attributes = [
        'classificacao' => 'revenda',
        'origem' => 'nacional',
    ];

    protected $fillable = [
        'codigo_interno',
        'categoria_produto_id',
        'fornecedor_id',
        'nome',
        'marca',
        'descricao',
        'observacao',
        'unidade_medida',
        'status',
        'ncm',
        'estoque_minimo',
        'estoque_fisico',
        'estoque_reservado',
        'peso_unitario_kg',
        'preco_tabela',
        'preco_minimo',
        'custo_base_formacao',
        'preco_sugerido',
        'classificacao',
        'origem',
        'ativo',
    ];

    protected $casts = [
        'categoria_produto_id' => 'integer',
        'fornecedor_id' => 'string',
        'estoque_minimo' => 'decimal:4',
        'estoque_fisico' => 'decimal:4',
        'estoque_reservado' => 'decimal:4',
        'peso_unitario_kg' => 'decimal:4',
        'preco_tabela' => 'decimal:2',
        'preco_minimo' => 'decimal:2',
        'custo_base_formacao' => 'decimal:4',
        'preco_sugerido' => 'decimal:2',
        'classificacao' => ProdutoClassificacao::class,
        'origem' => ProdutoOrigem::class,
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

    public static function classificacaoOptions(): array
    {
        return ProdutoClassificacao::options();
    }

    public static function origemOptions(): array
    {
        return ProdutoOrigem::options();
    }

    public function categoriaProduto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_produto_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id', 'uuid');
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

    public function reservas(): HasMany
    {
        return $this->hasMany(ProdutoReserva::class, 'produto_id');
    }

    public function ordensProducao(): HasMany
    {
        return $this->hasMany(OrdemProducao::class, 'produto_id');
    }

    public function isFabricado(): bool
    {
        return $this->classificacao === ProdutoClassificacao::Fabricado;
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

    public function estoqueDisponivel(): float
    {
        return round(
            (float) ($this->estoqueAtual() ?? 0) - (float) ($this->estoque_reservado ?? 0),
            4,
        );
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
        $estoqueAtual = $this->possuiHistoricoEstoque() ? $this->estoqueDisponivel() : null;
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
