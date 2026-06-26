<?php

namespace App\Models\Empresas;

use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Models\Status\StatusHomologacao;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;


class Fornecedor extends Model
{
    protected $table = "fornecedores";
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    protected $fillable = [
        'uuid',
        'codigo_interno',

        'id_categoria_fornecimento',
        'id_status_homologacao',

        'id_prazo_pagamento',
        'id_forma_pagamento',

        'razao_social',
        'nome_fantasia',
        'cnpj',
        'inscricao_estadual',

        'nome_completo',
        'cargo',
        'email',
        'telefone',

        'cep',
        'uf',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',

        'observacoes',
    ];

    protected $casts = [
        // IDs
        'id_categoria_fornecimento' => 'integer',
        'id_status_homologacao'     => 'integer',
        'id_prazo_pagamento'        => 'integer',
        'id_forma_pagamento'        => 'integer',

        // Strings estruturadas
        'uuid'                => 'string',
        'codigo_interno'      => 'string',
        'cnpj'                => 'string',
        'inscricao_estadual'  => 'string',
        'cep'                 => 'string',
        'uf'                  => 'string',
        'telefone'            => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $model): void {
            $model->cnpj = TaxIdentifier::normalizeForStorage($model->cnpj);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function categoriaFornecimento(): BelongsTo
    {
        return $this->belongsTo(
            CategoriaFornecimento::class,
            'id_categoria_fornecimento'
        );
    }

    public function statusHomologacao(): BelongsTo
    {
        return $this->belongsTo(
            StatusHomologacao::class,
            'id_status_homologacao'
        );
    }


    public function prazoPagamento(): BelongsTo
    {
        return $this->belongsTo(
            PrazoPagamento::class,
            'id_prazo_pagamento'
        );
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(
            FormaPagamento::class,
            'id_forma_pagamento'
        );
    }

    public function insumosPreferenciais(): HasMany
    {
        return $this->hasMany(Insumo::class, 'fornecedor_id', 'uuid');
    }

    public function produtosPreferenciais(): HasMany
    {
        return $this->hasMany(Produto::class, 'fornecedor_id', 'uuid');
    }
}
