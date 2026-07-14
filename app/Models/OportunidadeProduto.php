<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
            $oportunidadeId = (int) ($oportunidadeProduto->oportunidade_id ?: $oportunidadeProduto->getOriginal('oportunidade_id'));
            $oportunidade = Oportunidade::query()->with('etapa')->find($oportunidadeId);

            if ($oportunidade && Auth::check() && ! Gate::forUser(Auth::user())->allows('update', $oportunidade)) {
                abort(403, 'Voce nao pode alterar itens desta oportunidade.');
            }

            if ($oportunidade && ! $oportunidade->canEditCommercially()) {
                throw ValidationException::withMessages([
                    'oportunidade_id' => 'Reabra a oportunidade perdida antes de alterar itens; oportunidades convertidas permanecem congeladas.',
                ]);
            }

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

        static::deleting(function (self $oportunidadeProduto): void {
            $oportunidade = Oportunidade::query()->with('etapa')->find($oportunidadeProduto->oportunidade_id);

            if ($oportunidade && ! $oportunidade->canEditCommercially()) {
                throw ValidationException::withMessages([
                    'oportunidade_id' => 'Itens de oportunidades perdidas ou convertidas nao podem ser excluidos.',
                ]);
            }
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
