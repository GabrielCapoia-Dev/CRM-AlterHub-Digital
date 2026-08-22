<?php

namespace App\Models\Clientes;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\ClienteTransportadora;
use App\Models\Oportunidade;
use App\Models\Status\StatusCliente;
use App\Models\Transportadora;
use App\Models\VendaOperacaoPedido;
use App\Services\Acesso\RoleService;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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
        'vendedor_id',

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
        'vendedor_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cliente): void {
            if (blank($cliente->codigo_interno)) {
                $cliente->codigo_interno = static::generateCodigoInterno();
            }

        });

        static::saving(function (self $cliente): void {
            $actor = Auth::user();

            if (! $cliente->exists && $actor?->hasRole(RolesEnum::Vendedor->value)) {
                $cliente->vendedor_id = $actor->id;
            } elseif ($actor
                && ! app(RoleService::class)->podeEscolherVendedor($actor)
                && $cliente->isDirty('vendedor_id')) {
                // A autorizacao precisa existir no dominio, pois campos
                // desabilitados no navegador ainda podem ser adulterados.
                $cliente->vendedor_id = $cliente->exists
                    ? $cliente->getOriginal('vendedor_id')
                    : null;
            }

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

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(
            Oportunidade::class,
            'cliente_id'
        );
    }

    public function vendasOperacaoPedidos(): HasMany
    {
        return $this->hasMany(VendaOperacaoPedido::class, 'cliente_id');
    }

    public function clienteTransportadoras(): HasMany
    {
        return $this->hasMany(ClienteTransportadora::class, 'cliente_id');
    }

    public function transportadoras(): BelongsToMany
    {
        return $this->belongsToMany(Transportadora::class, 'cliente_transportadora')
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

    public function scopeLookupByCodigoOuCnpj(Builder $query, string $termo): Builder
    {
        $termo = trim($termo);
        $normalized = TaxIdentifier::normalizeForLookup($termo) ?? '';

        return $query->where(function (Builder $builder) use ($termo, $normalized): void {
            $builder->whereRaw('LOWER(codigo_interno) = ?', [mb_strtolower($termo)]);

            if ($normalized !== '') {
                $builder->orWhereRaw(
                    TaxIdentifier::comparableExpression('cnpj').' = ?',
                    [$normalized],
                );
            }
        });
    }

    public function scopeVisiveisPara(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(RolesEnum::Vendedor->value)) {
            return $query->where('vendedor_id', $user->id);
        }

        return $query;
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
