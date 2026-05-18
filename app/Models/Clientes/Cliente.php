<?php

namespace App\Models\Clientes;

use App\Models\Categorias\CategoriaSegmento;
use App\Models\Oportunidade;
use App\Models\Status\StatusCliente;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Database\Eloquent\Builder;
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
        'id_status_cliente' => 'integer',
        'id_categoria_segmento' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cliente): void {
            if (blank($cliente->codigo_interno)) {
                $cliente->codigo_interno = static::generateCodigoInterno();
            }
        });

        static::saving(function (self $cliente): void {
            $cliente->cnpj = TaxIdentifier::normalizeForStorage($cliente->cnpj);
        });
    }

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

    public function scopeLookupByCodigoOuCnpj(Builder $query, string $termo): Builder
    {
        $termo = trim($termo);
        $normalized = TaxIdentifier::normalizeForLookup($termo) ?? '';

        return $query->where(function (Builder $builder) use ($termo, $normalized): void {
            $builder->whereRaw('LOWER(codigo_interno) = ?', [mb_strtolower($termo)]);

            if ($normalized !== '') {
                $builder->orWhereRaw(
                    TaxIdentifier::comparableExpression('cnpj') . ' = ?',
                    [$normalized],
                );
            }
        });
    }

    protected static function generateCodigoInterno(): string
    {
        $anoAtual = now()->format('Y');
        $prefixo = "CLI-{$anoAtual}-";

        $ultimoNumero = static::query()
            ->where('codigo_interno', 'like', "{$prefixo}%")
            ->pluck('codigo_interno')
            ->map(function (?string $codigo) use ($prefixo): int {
                if (! is_string($codigo) || ! str_starts_with($codigo, $prefixo)) {
                    return 0;
                }

                return (int) str_replace($prefixo, '', $codigo);
            })
            ->max();

        return sprintf('%s%03d', $prefixo, ((int) $ultimoNumero) + 1);
    }
}
