<?php

namespace App\Services\Fiscal;

use App\Enum\UnidadeFederativa;
use App\Models\Produto;
use App\Models\RegraTributaria;
use App\Models\VendaOperacaoPedido;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegraTributariaService
{
    protected const RATE_FIELDS = [
        'aliquota_icms',
        'aliquota_icms_st',
        'aliquota_ipi',
        'aliquota_pis',
        'aliquota_cofins',
        'aliquota_fcp',
        'reducao_base_calculo',
    ];

    public function criarVersao(array $data): RegraTributaria
    {
        return DB::transaction(function () use ($data): RegraTributaria {
            $uf = $this->normalizeUf($data['uf_destino'] ?? null);
            $ncm = RegraTributaria::normalizeNcm($data['ncm'] ?? null);
            $inicio = Carbon::parse($data['vigencia_inicio'] ?? now())->startOfDay();
            $fim = filled($data['vigencia_fim'] ?? null)
                ? Carbon::parse($data['vigencia_fim'])->startOfDay()
                : null;

            if ($fim?->lt($inicio)) {
                throw ValidationException::withMessages([
                    'vigencia_fim' => 'A vigencia final nao pode ser anterior a vigencia inicial.',
                ]);
            }

            $this->validateRates($data);

            $versions = RegraTributaria::query()
                ->where('uf_destino', $uf->value)
                ->when(
                    $ncm === null,
                    fn (Builder $query): Builder => $query->whereNull('ncm'),
                    fn (Builder $query): Builder => $query->where('ncm', $ncm),
                )
                ->lockForUpdate();

            $versao = ((int) (clone $versions)->max('versao')) + 1;
            $nextStart = (clone $versions)
                ->whereDate('vigencia_inicio', '>', $inicio->toDateString())
                ->orderBy('vigencia_inicio')
                ->value('vigencia_inicio');

            if ($nextStart !== null) {
                $nextStartDate = Carbon::parse($nextStart)->startOfDay();

                if ($fim === null || $fim->gte($nextStartDate)) {
                    $fim = $nextStartDate->subDay();
                }
            }

            (clone $versions)
                ->whereDate('vigencia_inicio', '<', $inicio->toDateString())
                ->where(function (Builder $query) use ($inicio): void {
                    $query
                        ->whereNull('vigencia_fim')
                        ->orWhereDate('vigencia_fim', '>=', $inicio->toDateString());
                })
                ->update(['vigencia_fim' => $inicio->copy()->subDay()->toDateString()]);

            return RegraTributaria::query()->create([
                ...$data,
                'nome' => trim((string) ($data['nome'] ?? 'Regra '.$uf->value)),
                'uf_destino' => $uf,
                'ncm' => $ncm,
                'versao' => $versao,
                'vigencia_inicio' => $inicio->toDateString(),
                'vigencia_fim' => $fim?->toDateString(),
                'ativo' => $data['ativo'] ?? true,
            ]);
        });
    }

    public function createVersion(array $data): RegraTributaria
    {
        return $this->criarVersao($data);
    }

    public function resolver(
        string|UnidadeFederativa $ufDestino,
        ?string $ncm = null,
        DateTimeInterface|string|null $data = null,
    ): ?RegraTributaria {
        $uf = $this->normalizeUf($ufDestino);
        $normalizedNcm = RegraTributaria::normalizeNcm($ncm);
        $date = $data ? Carbon::parse($data) : now();

        return RegraTributaria::query()
            ->where('ativo', true)
            ->where('uf_destino', $uf->value)
            ->vigenteEm($date)
            ->when(
                $normalizedNcm === null,
                fn (Builder $query): Builder => $query->whereNull('ncm'),
                fn (Builder $query): Builder => $query->where(function (Builder $specific) use ($normalizedNcm): void {
                    $specific->where('ncm', $normalizedNcm)->orWhereNull('ncm');
                }),
            )
            ->when(
                $normalizedNcm !== null,
                fn (Builder $query): Builder => $query->orderByRaw(
                    'CASE WHEN ncm = ? THEN 0 ELSE 1 END',
                    [$normalizedNcm],
                ),
            )
            ->orderByDesc('vigencia_inicio')
            ->orderByDesc('versao')
            ->first();
    }

    public function resolve(
        string|UnidadeFederativa $ufDestino,
        ?string $ncm = null,
        DateTimeInterface|string|null $data = null,
    ): ?RegraTributaria {
        return $this->resolver($ufDestino, $ncm, $data);
    }

    public function resolverParaProduto(
        Produto $produto,
        string|UnidadeFederativa $ufDestino,
        DateTimeInterface|string|null $data = null,
    ): ?RegraTributaria {
        return $this->resolver($ufDestino, $produto->ncm, $data);
    }

    /**
     * @return array{regra: RegraTributaria|null, tributos: array<string, float>, total: float}
     */
    public function calcular(
        float $baseCalculo,
        string|UnidadeFederativa $ufDestino,
        ?string $ncm = null,
        DateTimeInterface|string|null $data = null,
    ): array {
        $regra = $this->resolver($ufDestino, $ncm, $data);
        $tributos = $regra?->calcularSobre($baseCalculo) ?? [];

        return [
            'regra' => $regra,
            'tributos' => $tributos,
            'total' => round((float) array_sum($tributos), 2),
        ];
    }

    public function aplicarNaVenda(VendaOperacaoPedido $pedido): void
    {
        DB::transaction(function () use ($pedido): void {
            $locked = VendaOperacaoPedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido->id);
            $locked->load(['cliente', 'vendasOperacao.produto']);
            $uf = $this->normalizeUf($locked->cliente?->uf);
            $data = $locked->data_venda ?? now();

            foreach ($locked->vendasOperacao as $linha) {
                $ncm = RegraTributaria::normalizeNcm($linha->produto?->ncm);
                $regra = $this->resolver($uf, $ncm, $data);

                if (! $regra) {
                    throw ValidationException::withMessages([
                        "vendasOperacao.{$linha->id}.regra_tributaria" => sprintf(
                            'Nao existe regra tributaria vigente para %s%s.',
                            $uf->value,
                            $ncm ? " e NCM {$ncm}" : '',
                        ),
                    ]);
                }

                $base = round((float) $linha->receita_bruta, 2);
                $tributos = $regra->calcularSobre($base);
                $icms = (float) ($tributos['icms'] ?? 0);
                $outros = round((float) collect($tributos)->except('icms')->sum(), 2);
                $outrosAliquota = round(
                    (float) $regra->aliquota_icms_st
                    + (float) $regra->aliquota_ipi
                    + (float) $regra->aliquota_pis
                    + (float) $regra->aliquota_cofins
                    + (float) $regra->aliquota_fcp,
                    4,
                );
                $receitaLiquida = round($base - $icms - $outros, 2);

                $linha->forceFill([
                    'regra_tributaria_id' => $regra->id,
                    'regra_tributaria_versao_snapshot' => $regra->versao,
                    'uf_destino_snapshot' => $uf->value,
                    'ncm_snapshot' => $ncm,
                    'icms_aliquota' => $regra->aliquota_icms,
                    'icms_valor' => $icms,
                    'outros_impostos_aliquota' => $outrosAliquota,
                    'outros_impostos_valor' => $outros,
                    'receita_liquida' => $receitaLiquida,
                    'lucro_bruto' => round($base - (float) $linha->custo_total_snapshot, 2),
                    'lucro_apos_impostos' => round($receitaLiquida - (float) $linha->custo_total_snapshot, 2),
                ])->save();
            }

            $locked->forceFill([
                'receita_liquida_total' => round((float) $locked->vendasOperacao()->sum('receita_liquida'), 2),
                'lucro_bruto_total' => round((float) $locked->vendasOperacao()->sum('lucro_bruto'), 2),
                'lucro_apos_impostos_total' => round((float) $locked->vendasOperacao()->sum('lucro_apos_impostos'), 2),
            ])->save();
        });
    }

    protected function normalizeUf(mixed $value): UnidadeFederativa
    {
        $uf = $value instanceof UnidadeFederativa
            ? $value
            : UnidadeFederativa::tryFrom(mb_strtoupper(trim((string) $value)));

        if (! $uf) {
            throw ValidationException::withMessages([
                'uf_destino' => 'Informe uma UF de destino valida.',
            ]);
        }

        return $uf;
    }

    protected function validateRates(array $data): void
    {
        $errors = [];

        foreach (self::RATE_FIELDS as $field) {
            $value = (float) ($data[$field] ?? 0);

            if ($value < 0 || $value > 100) {
                $errors[$field] = 'A aliquota deve ficar entre 0 e 100 por cento.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
