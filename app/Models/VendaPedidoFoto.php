<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendaPedidoFoto extends Model
{
    use SoftDeletes;

    protected $table = 'venda_pedido_fotos';

    protected $fillable = [
        'venda_operacao_pedido_id',
        'venda_operacao_id',
        'venda_operacao_lote_id',
        'user_id',
        'disk',
        'path',
        'nome_original',
        'mime_type',
        'tamanho_bytes',
        'descricao',
        'removida_por',
        'removida_em',
    ];

    protected $casts = [
        'venda_operacao_pedido_id' => 'integer',
        'venda_operacao_id' => 'integer',
        'venda_operacao_lote_id' => 'integer',
        'user_id' => 'integer',
        'tamanho_bytes' => 'integer',
        'removida_por' => 'integer',
        'removida_em' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            VendaOperacaoPedido::class,
            'venda_operacao_pedido_id',
        );
    }

    public function vendaOperacao(): BelongsTo
    {
        return $this->belongsTo(VendaOperacao::class, 'venda_operacao_id');
    }

    public function item(): BelongsTo
    {
        return $this->vendaOperacao();
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(VendaOperacaoLote::class, 'venda_operacao_lote_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function removidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removida_por');
    }
}
