<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Empresas\Fornecedor;

class StatusHomologacao extends Model
{
    protected $table = 'status_homologacao';

    protected $fillable = [
        'nome',
    ];

    protected $casts = [
        'nome'       => 'string',
    ];

    public function fornecedores(): HasMany
    {
        return $this->hasMany(
            Fornecedor::class,
            'id_status_homologacao'
        );
    }
}