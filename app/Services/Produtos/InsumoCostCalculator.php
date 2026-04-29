<?php

namespace App\Services\Produtos;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class InsumoCostCalculator
{
    public function prepareForPersistence(array $data): array
    {
        $normalized = $this->normalizePayload($data);

        $this->validate($normalized);

        $calculation = $this->calculate(
            origem: $normalized['origem'],
            custoUnitarioBrl: $normalized['custo_referencia'],
            custoMoedaOrigem: $normalized['custo_moeda_origem'],
            taxaCambio: $normalized['taxa_cambio'],
            fatores: $normalized['insumoFatoresCusto'],
        );

        $normalized['valor_convertido_brl'] = $calculation['valor_convertido_brl'];
        $normalized['custo_nacionalizado'] = $calculation['custo_nacionalizado'];
        $normalized['custo_referencia'] = $calculation['custo_referencia'];

        if ($normalized['origem'] === 'nacional') {
            $normalized['moeda_origem'] = null;
            $normalized['custo_moeda_origem'] = null;
            $normalized['taxa_cambio'] = null;
            $normalized['insumoFatoresCusto'] = [];
        }

        return $normalized;
    }

    public function calculate(
        string $origem,
        ?float $custoUnitarioBrl,
        ?float $custoMoedaOrigem,
        ?float $taxaCambio,
        array $fatores = [],
    ): array {
        $fatoresColetados = collect($fatores);

        $fatorDivisor = $fatoresColetados
            // Valores fracionarios vindos do formulario representam o fator aplicado ao custo importado.
            ->filter(fn (array $fator): bool => $this->isImportedDivisorFactor($fator))
            ->reduce(
                fn (float $acumulado, array $fator): float => $acumulado * (float) ($fator['valor'] ?? 1),
                1.0,
            );

        $somaFixosBrl = $fatoresColetados
            ->filter(fn (array $fator): bool => ($fator['tipo'] ?? null) === 'valor_fixo_brl' && ! $this->isImportedDivisorFactor($fator))
            ->sum(fn (array $fator): float => (float) ($fator['valor'] ?? 0));

        $somaPercentuais = $fatoresColetados
            ->filter(fn (array $fator): bool => ($fator['tipo'] ?? null) === 'percentual')
            ->sum(fn (array $fator): float => (float) ($fator['valor'] ?? 0));

        if ($origem === 'importado') {
            $valorConvertido = round(((float) $custoMoedaOrigem) * ((float) $taxaCambio), 4);
            $custoNacionalizado = round(
                (($valorConvertido + $somaFixosBrl) / $fatorDivisor) * (1 + ($somaPercentuais / 100)),
                4,
            );

            return [
                'valor_convertido_brl' => $valorConvertido,
                'custo_nacionalizado' => $custoNacionalizado,
                'custo_referencia' => $custoNacionalizado,
                'fator_divisor' => round($fatorDivisor, 6),
                'soma_fixos_brl' => round($somaFixosBrl, 4),
                'soma_percentuais' => round($somaPercentuais, 4),
            ];
        }

        $custoUnitarioBrl = round((float) $custoUnitarioBrl, 4);

        return [
            'valor_convertido_brl' => $custoUnitarioBrl,
            'custo_nacionalizado' => $custoUnitarioBrl,
            'custo_referencia' => $custoUnitarioBrl,
            'fator_divisor' => 1.0,
            'soma_fixos_brl' => 0.0,
            'soma_percentuais' => 0.0,
        ];
    }

    public function summarizeFromState(array $state): array
    {
        $normalized = $this->normalizePayload($state);

        return $this->calculate(
            origem: $normalized['origem'],
            custoUnitarioBrl: $normalized['custo_referencia'],
            custoMoedaOrigem: $normalized['custo_moeda_origem'],
            taxaCambio: $normalized['taxa_cambio'],
            fatores: $normalized['insumoFatoresCusto'],
        );
    }

    public function validate(array $data): void
    {
        $messages = [];

        if (blank($data['fornecedor_id'] ?? null)) {
            $messages['fornecedor_id'] = 'Selecione o fornecedor preferencial do insumo.';
        }

        if (! in_array($data['origem'], ['nacional', 'importado'], true)) {
            $messages['origem'] = 'Selecione uma origem válida para o insumo.';
        }

        if ($data['origem'] === 'nacional' && ($data['custo_referencia'] ?? 0) <= 0) {
            $messages['custo_referencia'] = 'Informe um custo unitário em BRL maior que zero.';
        }

        if ($data['origem'] === 'importado') {
            if (! in_array($data['moeda_origem'], ['USD', 'EUR'], true)) {
                $messages['moeda_origem'] = 'Selecione a moeda de origem para o insumo importado.';
            }

            if (($data['custo_moeda_origem'] ?? 0) <= 0) {
                $messages['custo_moeda_origem'] = 'Informe o custo na moeda de origem.';
            }

            if (($data['taxa_cambio'] ?? 0) <= 0) {
                $messages['taxa_cambio'] = 'Informe a taxa de câmbio manual utilizada.';
            }
        }

        foreach ($data['insumoFatoresCusto'] as $index => $fator) {
            if (blank($fator['nome'] ?? null)) {
                $messages["insumoFatoresCusto.$index.nome"] = 'Nomeie cada fator de custo informado.';
            }

            if (! in_array($fator['tipo'] ?? null, ['percentual', 'valor_fixo_brl'], true)) {
                $messages["insumoFatoresCusto.$index.tipo"] = 'Selecione um tipo válido para o fator de custo.';
            }

            if (($fator['valor'] ?? 0) < 0) {
                $messages["insumoFatoresCusto.$index.valor"] = 'O valor do fator de custo não pode ser negativo.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected function normalizePayload(array $data): array
    {
        $fatores = collect(Arr::get($data, 'insumoFatoresCusto', []))
            ->filter(fn (mixed $fator): bool => is_array($fator))
            ->values()
            ->map(fn (array $fator, int $index): array => [
                'id' => $fator['id'] ?? null,
                'nome' => trim((string) ($fator['nome'] ?? '')),
                'tipo' => $fator['tipo'] ?? null,
                'valor' => $this->toNullableFloat($fator['valor']) ?? 0.0,
                'ordem' => $fator['ordem'] ?? $index,
            ])
            ->all();

        return [
            ...$data,
            'fornecedor_id' => $data['fornecedor_id'] ?? null,
            'origem' => $data['origem'] ?? 'nacional',
            'moeda_origem' => $data['moeda_origem'] ?? null,
            'custo_referencia' => $this->toNullableFloat($data['custo_referencia'] ?? null),
            'custo_moeda_origem' => $this->toNullableFloat($data['custo_moeda_origem'] ?? null),
            'taxa_cambio' => $this->toNullableFloat($data['taxa_cambio'] ?? null),
            'insumoFatoresCusto' => $fatores,
        ];
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 6);
    }

    protected function isImportedDivisorFactor(array $fator): bool
    {
        if (($fator['tipo'] ?? null) !== 'valor_fixo_brl') {
            return false;
        }

        $valor = (float) ($fator['valor'] ?? 0);

        return $valor > 0 && $valor < 1;
    }
}
