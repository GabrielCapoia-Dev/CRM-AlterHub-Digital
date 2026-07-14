<?php

namespace App\Models;

use App\Enum\UnidadeFederativa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RegraTributaria extends Model
{
    protected $table = 'regras_tributarias';

    protected $fillable = [
        'nome',
        'uf_destino',
        'ncm',
        'versao',
        'vigencia_inicio',
        'vigencia_fim',
        'aliquota_icms',
        'aliquota_icms_st',
        'aliquota_ipi',
        'aliquota_pis',
        'aliquota_cofins',
        'aliquota_fcp',
        'reducao_base_calculo',
        'ativo',
        'metadados',
        'observacao',
    ];

    protected $casts = [
        'uf_destino' => UnidadeFederativa::class,
        'versao' => 'integer',
        'vigencia_inicio' => 'date',
        'vigencia_fim' => 'date',
        'aliquota_icms' => 'decimal:4',
        'aliquota_icms_st' => 'decimal:4',
        'aliquota_ipi' => 'decimal:4',
        'aliquota_pis' => 'decimal:4',
        'aliquota_cofins' => 'decimal:4',
        'aliquota_fcp' => 'decimal:4',
        'reducao_base_calculo' => 'decimal:4',
        'ativo' => 'boolean',
        'metadados' => 'array',
    ];

    public function setNcmAttribute(mixed $value): void
    {
        $this->attributes['ncm'] = static::normalizeNcm($value);
    }

    public function scopeVigenteEm(Builder $query, mixed $data): Builder
    {
        $date = Carbon::parse($data)->toDateString();

        return $query
            ->whereDate('vigencia_inicio', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder
                    ->whereNull('vigencia_fim')
                    ->orWhereDate('vigencia_fim', '>=', $date);
            });
    }

    /**
     * @return array<string, float>
     */
    public function calcularSobre(float $baseCalculo): array
    {
        $base = round(max(0, $baseCalculo) * (1 - ((float) $this->reducao_base_calculo / 100)), 2);

        return collect([
            'icms' => $this->aliquota_icms,
            'icms_st' => $this->aliquota_icms_st,
            'ipi' => $this->aliquota_ipi,
            'pis' => $this->aliquota_pis,
            'cofins' => $this->aliquota_cofins,
            'fcp' => $this->aliquota_fcp,
        ])->mapWithKeys(fn (mixed $aliquota, string $tributo): array => [
            $tributo => round($base * ((float) $aliquota / 100), 2),
        ])->all();
    }

    public static function normalizeNcm(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits === '' ? null : substr($digits, 0, 8);
    }
}
