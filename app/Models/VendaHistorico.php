<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaHistorico extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'venda_historicos';

    protected $fillable = [
        'venda_operacao_pedido_id',
        'user_id',
        'evento',
        'status_anterior',
        'status_novo',
        'justificativa',
        'metadados',
    ];

    protected $casts = [
        'venda_operacao_pedido_id' => 'integer',
        'user_id' => 'integer',
        'metadados' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(VendaOperacaoPedido::class, 'venda_operacao_pedido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
