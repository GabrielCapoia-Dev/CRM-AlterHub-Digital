<?php

namespace App\Models\Clientes;

use App\Models\Status\StatusCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'codigo_interno',
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'segmento',

        'id_status_cliente',

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
        'id_status_cliente' => 'integer',
    ];

    public function statusCliente(): BelongsTo
    {
        return $this->belongsTo(
            StatusCliente::class,
            'id_status_cliente'
        );
    }
}