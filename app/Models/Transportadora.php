<?php

namespace App\Models;

use App\Enum\ModalidadeEntrega;
use App\Models\Clientes\Cliente;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transportadora extends Model
{
    protected $table = 'transportadoras';

    protected $attributes = [
        'ativo' => true,
    ];

    protected $fillable = [
        'codigo_interno',
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'contato_nome',
        'email',
        'telefone',
        'prazo_estimado_dias',
        'valor_frete_custo_padrao',
        'valor_frete_cobrado_padrao',
        'modalidades_entrega',
        'ativo',
        'observacao',
    ];

    protected $casts = [
        'prazo_estimado_dias' => 'integer',
        'valor_frete_custo_padrao' => 'decimal:2',
        'valor_frete_cobrado_padrao' => 'decimal:2',
        'modalidades_entrega' => 'array',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transportadora): void {
            if (blank($transportadora->codigo_interno)) {
                $transportadora->codigo_interno = static::generateCodigoInterno();
            }
        });

        static::saving(function (self $transportadora): void {
            $transportadora->cnpj = TaxIdentifier::normalizeForStorage($transportadora->cnpj);

            if (blank($transportadora->modalidades_entrega)) {
                $transportadora->modalidades_entrega = [ModalidadeEntrega::Transportadora->value];
            }
        });
    }

    public function clienteTransportadoras(): HasMany
    {
        return $this->hasMany(ClienteTransportadora::class, 'transportadora_id');
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_transportadora')
            ->withPivot([
                'codigo_cliente_transportadora',
                'preferencial',
                'modalidade_entrega_padrao',
                'valor_frete_custo_padrao',
                'valor_frete_cobrado_padrao',
                'observacao',
            ])
            ->withTimestamps();
    }

    protected static function generateCodigoInterno(): string
    {
        $last = (int) static::query()
            ->where('codigo_interno', 'like', 'TRP-%')
            ->pluck('codigo_interno')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D+/', '', $codigo))
            ->max();

        return sprintf('TRP-%04d', $last + 1);
    }
}
