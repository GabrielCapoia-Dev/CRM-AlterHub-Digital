<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OportunidadeProduto extends Model
{
    protected $table = 'oportunidade_produtos';

    protected $fillable = [
        'oportunidade_id',
        'produto_id',
        'quantidade',
        'preco_negociado',
        'desconto_percentual',
        'desconto_aprovado_por',
        'desconto_aprovado_em',
        'observacao',
    ];

    protected $casts = [
        'oportunidade_id' => 'integer',
        'produto_id' => 'integer',
        'quantidade' => 'decimal:4',
        'preco_negociado' => 'decimal:2',
        'desconto_percentual' => 'decimal:2',
        'desconto_aprovado_por' => 'integer',
        'desconto_aprovado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $oportunidadeProduto): void {
            $produto = $oportunidadeProduto->produto_id
                ? Produto::query()->find($oportunidadeProduto->produto_id)
                : null;

            $precoMinimo = $produto?->preco_minimo !== null ? (float) $produto->preco_minimo : 0.0;
            $precoNegociado = $oportunidadeProduto->preco_negociado !== null ? (float) $oportunidadeProduto->preco_negociado : 0.0;
            $precoTabela = $produto?->preco_tabela !== null ? (float) $produto->preco_tabela : 0.0;

            if ($precoTabela > 0 && $precoNegociado > 0) {
                $oportunidadeProduto->desconto_percentual = round(max(0, min(100, (1 - ($precoNegociado / $precoTabela)) * 100)), 2);
            }

            if ($precoMinimo > 0 && $precoNegociado > 0 && $precoNegociado < $precoMinimo && ! $oportunidadeProduto->desconto_aprovado_por) {
                throw ValidationException::withMessages([
                    'preco_negociado' => 'Este desconto deixa o preco final abaixo do minimo do produto e precisa de aprovacao.',
                ]);
            }

            if (! ($precoMinimo > 0 && $precoNegociado > 0 && $precoNegociado < $precoMinimo)) {
                $oportunidadeProduto->desconto_aprovado_por = null;
                $oportunidadeProduto->desconto_aprovado_em = null;
            }
        });

        static::saved(function (self $oportunidadeProduto): void {
            Oportunidade::query()
                ->find($oportunidadeProduto->oportunidade_id)
                ?->recalcularValorEstimado();
        });

        static::deleted(function (self $oportunidadeProduto): void {
            Oportunidade::query()
                ->find($oportunidadeProduto->oportunidade_id)
                ?->recalcularValorEstimado();
        });
    }

    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(
            Oportunidade::class,
            'oportunidade_id',
        );
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(
            Produto::class,
            'produto_id',
        );
    }

    public function descontoAprovadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'desconto_aprovado_por',
        );
    }

    public function precoUnitario(): float
    {
        $negociado = $this->preco_negociado !== null ? (float) $this->preco_negociado : null;

        if ($negociado !== null && $negociado > 0) {
            return round($negociado, 2);
        }

        return round((float) ($this->produto?->preco_tabela ?? 0), 2);
    }

    public function subtotalEstimado(): float
    {
        return round(((float) ($this->quantidade ?? 0)) * $this->precoUnitario(), 2);
    }
}
