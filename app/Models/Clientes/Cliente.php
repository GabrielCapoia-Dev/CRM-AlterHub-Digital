<?php

namespace App\Models\Clientes;

use App\Models\Oportunidade;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Status\StatusCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'codigo_interno',
        'razao_social',
        'nome_fantasia',
        'cnpj',

        'id_status_cliente',
        'id_categoria_segmento',

        'nome_completo',
        'cargo',
        'email',
        'telefone',

        'cep',
        'logradouro',
        'uf',
        'numero',
        'complemento',
        'bairro',
        'cidade',

        'observacao',
    ];

    protected $casts = [
        'id_status_cliente'     => 'integer',
        'id_categoria_segmento' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function statusCliente(): BelongsTo
    {
        return $this->belongsTo(
            StatusCliente::class,
            'id_status_cliente'
        );
    }

    public function categoriaSegmento(): BelongsTo
    {
        return $this->belongsTo(
            CategoriaSegmento::class,
            'id_categoria_segmento'
        );
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(
            Oportunidade::class,
            'cliente_id'
        );
    }
}
