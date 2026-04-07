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

    public static function statusOptions(): array
    {
        return [
            'em_registro' => 'Em registro',
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
}
