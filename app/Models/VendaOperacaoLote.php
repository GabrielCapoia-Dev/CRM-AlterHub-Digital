<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendaOperacaoLote extends Model
{
    use SoftDeletes;

    protected $table = 'venda_operacao_lotes';

    protected $fillable = [
        'venda_operacao_id',
        'user_id',
        'deleted_by',
        'numero_lote',
        'quantidade',
        'ano_fabricacao',
        'data_fabricacao',
        'data_validade',
        'observacao',
    ];

    protected $casts = [
        'venda_operacao_id' => 'integer',
        'user_id' => 'integer',
        'deleted_by' => 'integer',
        'quantidade' => 'decimal:4',
        'ano_fabricacao' => 'integer',
        'data_fabricacao' => 'date',
        'data_validade' => 'date',
    ];

    public function vendaOperacao(): BelongsTo
    {
        return $this->belongsTo(VendaOperacao::class, 'venda_operacao_id');
    }

    public function item(): BelongsTo
    {
        return $this->vendaOperacao();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(VendaPedidoFoto::class, 'venda_operacao_lote_id');
    }

    public function excluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
