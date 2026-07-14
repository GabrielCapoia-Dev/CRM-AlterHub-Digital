<?php

namespace App\Services\Logistica;

use App\Enum\ModalidadeEntrega;
use App\Models\Clientes\Cliente;
use App\Models\ClienteTransportadora;
use App\Models\Transportadora;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FreteService
{
    public function vincularTransportadora(
        Cliente|int $cliente,
        Transportadora|int $transportadora,
        array $data = [],
    ): ClienteTransportadora {
        return DB::transaction(function () use ($cliente, $transportadora, $data): ClienteTransportadora {
            $clienteModel = $this->resolveCliente($cliente);
            $transportadoraModel = $this->resolveTransportadora($transportadora);
            $this->validateAmounts($data);
            $modalidade = $this->normalizeModalidade($data['modalidade_entrega_padrao'] ?? null, false);
            $preferencial = filter_var($data['preferencial'] ?? false, FILTER_VALIDATE_BOOL);

            if ($preferencial) {
                ClienteTransportadora::query()
                    ->where('cliente_id', $clienteModel->id)
                    ->update([
                        'preferencial' => false,
                        'preferencial_cliente_id' => null,
                    ]);
            }

            return ClienteTransportadora::query()->updateOrCreate(
                [
                    'cliente_id' => $clienteModel->id,
                    'transportadora_id' => $transportadoraModel->id,
                ],
                [
                    'codigo_cliente_transportadora' => $this->trimOrNull($data['codigo_cliente_transportadora'] ?? null),
                    'preferencial' => $preferencial,
                    'modalidade_entrega_padrao' => $modalidade?->value,
                    'valor_frete_custo_padrao' => $this->nullableMoney($data['valor_frete_custo_padrao'] ?? null),
                    'valor_frete_cobrado_padrao' => $this->nullableMoney($data['valor_frete_cobrado_padrao'] ?? null),
                    'observacao' => $this->trimOrNull($data['observacao'] ?? null),
                ],
            );
        });
    }

    /**
     * @return array{
     *   transportadora_id: int|null,
     *   modalidade_entrega: string,
     *   valor_frete_custo: float,
     *   valor_frete_cobrado: float,
     *   margem_frete: float,
     *   prazo_estimado_dias: int|null
     * }
     */
    public function cotar(
        Cliente|int|null $cliente = null,
        Transportadora|int|null $transportadora = null,
        ModalidadeEntrega|string|null $modalidade = null,
        array $overrides = [],
    ): array {
        $clienteModel = $cliente !== null ? $this->resolveCliente($cliente) : null;
        $transportadoraModel = $transportadora !== null
            ? $this->resolveTransportadora($transportadora)
            : null;
        $link = null;

        if ($clienteModel && $transportadoraModel) {
            $link = ClienteTransportadora::query()
                ->where('cliente_id', $clienteModel->id)
                ->where('transportadora_id', $transportadoraModel->id)
                ->first();
        } elseif ($clienteModel && $transportadoraModel === null) {
            $link = ClienteTransportadora::query()
                ->with('transportadora')
                ->where('cliente_id', $clienteModel->id)
                ->where('preferencial', true)
                ->whereHas('transportadora', fn ($query) => $query->where('ativo', true))
                ->first();
            $transportadoraModel = $link?->transportadora;
        }

        $resolvedModalidade = $this->normalizeModalidade(
            $modalidade
                ?? $link?->modalidade_entrega_padrao
                ?? ($transportadoraModel ? ModalidadeEntrega::Transportadora : ModalidadeEntrega::Retirada),
        );

        if ($resolvedModalidade->requiresCarrier() && ! $transportadoraModel) {
            throw ValidationException::withMessages([
                'transportadora_id' => 'Selecione uma transportadora para esta modalidade de entrega.',
            ]);
        }

        $this->validateAmounts($overrides, ['valor_frete_custo', 'valor_frete_cobrado']);

        $custo = $this->firstMoney([
            $overrides['valor_frete_custo'] ?? null,
            $link?->valor_frete_custo_padrao,
            $transportadoraModel?->valor_frete_custo_padrao,
            0,
        ]);
        $cobrado = $this->firstMoney([
            $overrides['valor_frete_cobrado'] ?? null,
            $link?->valor_frete_cobrado_padrao,
            $transportadoraModel?->valor_frete_cobrado_padrao,
            0,
        ]);

        return [
            'transportadora_id' => $transportadoraModel?->id,
            'modalidade_entrega' => $resolvedModalidade->value,
            'valor_frete_custo' => $custo,
            'valor_frete_cobrado' => $cobrado,
            'margem_frete' => round($cobrado - $custo, 2),
            'prazo_estimado_dias' => $transportadoraModel?->prazo_estimado_dias,
        ];
    }

    public function quote(
        Cliente|int|null $cliente = null,
        Transportadora|int|null $transportadora = null,
        ModalidadeEntrega|string|null $modalidade = null,
        array $overrides = [],
    ): array {
        return $this->cotar($cliente, $transportadora, $modalidade, $overrides);
    }

    /**
     * Retorna somente os campos persistiveis esperados por uma remessa.
     *
     * @return array<string, int|float|string|null>
     */
    public function prepararPayloadRemessa(
        Cliente|int|null $cliente = null,
        Transportadora|int|null $transportadora = null,
        ModalidadeEntrega|string|null $modalidade = null,
        array $overrides = [],
    ): array {
        $quote = $this->cotar($cliente, $transportadora, $modalidade, $overrides);

        return collect($quote)->only([
            'transportadora_id',
            'modalidade_entrega',
            'valor_frete_custo',
            'valor_frete_cobrado',
        ])->all();
    }

    protected function resolveCliente(Cliente|int $cliente): Cliente
    {
        return $cliente instanceof Cliente
            ? $cliente
            : Cliente::query()->findOrFail($cliente);
    }

    protected function resolveTransportadora(Transportadora|int $transportadora): Transportadora
    {
        $model = $transportadora instanceof Transportadora
            ? $transportadora
            : Transportadora::query()->findOrFail($transportadora);

        if (! $model->ativo) {
            throw ValidationException::withMessages([
                'transportadora_id' => 'Selecione uma transportadora ativa.',
            ]);
        }

        return $model;
    }

    protected function normalizeModalidade(mixed $value, bool $required = true): ?ModalidadeEntrega
    {
        if ($value instanceof ModalidadeEntrega) {
            return $value;
        }

        if ($value === null || trim((string) $value) === '') {
            if (! $required) {
                return null;
            }

            throw ValidationException::withMessages([
                'modalidade_entrega' => 'Selecione uma modalidade de entrega.',
            ]);
        }

        $modalidade = ModalidadeEntrega::tryFrom((string) $value);

        if (! $modalidade) {
            throw ValidationException::withMessages([
                'modalidade_entrega' => 'Selecione uma modalidade de entrega valida.',
            ]);
        }

        return $modalidade;
    }

    /**
     * @param  list<string>|null  $fields
     */
    protected function validateAmounts(array $data, ?array $fields = null): void
    {
        $fields ??= [
            'valor_frete_custo_padrao',
            'valor_frete_cobrado_padrao',
        ];
        $errors = [];

        foreach ($fields as $field) {
            if (($data[$field] ?? null) !== null && (float) $data[$field] < 0) {
                $errors[$field] = 'O valor de frete nao pode ser negativo.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<mixed>  $values
     */
    protected function firstMoney(array $values): float
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return round((float) $value, 2);
            }
        }

        return 0.0;
    }

    protected function nullableMoney(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }

    protected function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
