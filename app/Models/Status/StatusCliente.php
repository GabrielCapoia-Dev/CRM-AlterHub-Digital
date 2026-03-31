<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Clientes\Cliente;

class StatusCliente extends Model
{
    protected $table = 'status_clientes';

    protected $fillable = [
        'nome',
    ];

    protected $casts = [
        'nome' => 'string',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(
            Cliente::class,
            'id_status_cliente'
        );
    }
}