<?php

namespace App\Services\CRM;

use App\Models\Clientes\Cliente;
use App\Models\Status\StatusCliente;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class OportunidadeClienteService
{
    public const MODE_EXISTING = 'existing';

    public const MODE_NEW = 'new';

    /**
     * @return array<string, mixed>
     */
    public function blankFormState(): array
    {
        return [
            'cliente_id' => null,
            'client_mode' => self::MODE_EXISTING,
            'client_lookup' => '',
            'client_lookup_status' => '',
            'client_lookup_message' => '',
            'client_razao_social' => '',
            'client_nome_fantasia' => '',
            'client_cnpj' => '',
            'client_segmento_id' => '',
            'client_status_id' => $this->defaultStatusId() ?? '',
            'client_nome_completo' => '',
            'client_cargo' => '',
            'client_email' => '',
            'client_telefone' => '',
            'client_cidade' => '',
            'client_uf' => '',
            'client_observacao' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function existingClientFormState(?Cliente $cliente): array
    {
        if (! $cliente) {
            return $this->blankFormState();
        }

        $cliente->loadMissing(['categoriaSegmento', 'statusCliente']);

        return [
            'cliente_id' => $cliente->id,
            'client_mode' => self::MODE_EXISTING,
            'client_lookup' => $cliente->codigo_interno ?: ($cliente->cnpj ?: ''),
            'client_lookup_status' => 'found',
            'client_lookup_message' => 'Cliente encontrado e vinculado a oportunidade.',
            'client_razao_social' => $cliente->razao_social,
            'client_nome_fantasia' => $cliente->nome_fantasia ?? '',
            'client_cnpj' => $cliente->cnpj ?? '',
            'client_segmento_id' => $cliente->id_categoria_segmento ?? '',
            'client_status_id' => $cliente->id_status_cliente ?? ($this->defaultStatusId() ?? ''),
            'client_nome_completo' => $cliente->nome_completo ?? '',
            'client_cargo' => $cliente->cargo ?? '',
            'client_email' => $cliente->email ?? '',
            'client_telefone' => $cliente->telefone ?? '',
            'client_cidade' => $cliente->cidade ?? '',
            'client_uf' => $cliente->uf ?? '',
            'client_observacao' => $cliente->observacao ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function newClientFormState(): array
    {
        return [
            ...$this->blankFormState(),
            'client_mode' => self::MODE_NEW,
            'client_lookup_status' => 'new',
            'client_lookup_message' => 'Preencha os dados do cliente sem sair do cadastro da oportunidade.',
        ];
    }

    public function defaultStatusId(): ?int
    {
        return StatusCliente::query()
            ->get(['id', 'nome'])
            ->sortBy(function (StatusCliente $status): array {
                $nome = mb_strtolower($status->nome);

                return match (true) {
                    str_contains($nome, 'prospec') => [0, $nome],
                    str_contains($nome, 'ativo') => [1, $nome],
                    default => [2, $nome],
                };
            })
            ->first()
            ?->id;
    }

    public function findByLookup(?string $lookup): ?Cliente
    {
        $lookup = trim((string) $lookup);

        if ($lookup === '') {
            return null;
        }

        return Cliente::query()
            ->with(['categoriaSegmento', 'statusCliente'])
            ->lookupByCodigoOuCnpj($lookup)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareOpportunityData(array $data): array
    {
        $cliente = $this->resolveClientFromData($data);

        return [
            'titulo' => trim((string) Arr::get($data, 'titulo')),
            'cliente_id' => $cliente->id,
            'etapa_id' => (int) Arr::get($data, 'etapa_id'),
            'user_id' => (int) Arr::get($data, 'user_id'),
            'temperatura' => Arr::get($data, 'temperatura'),
            'valor_estimado' => blank(Arr::get($data, 'valor_estimado')) ? null : (float) Arr::get($data, 'valor_estimado'),
            'motivo_fechamento' => blank(Arr::get($data, 'motivo_fechamento')) ? null : trim((string) Arr::get($data, 'motivo_fechamento')),
            'notas' => blank(Arr::get($data, 'notas')) ? null : trim((string) Arr::get($data, 'notas')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveClientFromData(array $data): Cliente
    {
        return Arr::get($data, 'client_mode') === self::MODE_NEW
            ? $this->createClientFromData($data)
            : $this->resolveExistingClient($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveExistingClient(array $data): Cliente
    {
        $clienteId = Arr::get($data, 'cliente_id');

        if (filled($clienteId)) {
            $cliente = Cliente::query()->find($clienteId);

            if ($cliente) {
                return $cliente;
            }
        }

        $cliente = $this->findByLookup(Arr::get($data, 'client_lookup'));

        if ($cliente) {
            return $cliente;
        }

        throw ValidationException::withMessages([
            'client_lookup' => 'Busque um cliente existente por código ou CNPJ, ou clique em Novo Cliente.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createClientFromData(array $data): Cliente
    {
        $cnpj = trim((string) Arr::get($data, 'client_cnpj'));

        if ($cnpj !== '') {
            $clienteExistente = $this->findByLookup($cnpj);

            if ($clienteExistente) {
                throw ValidationException::withMessages([
                    'client_cnpj' => 'Este CNPJ já está cadastrado. Use a busca para vincular o cliente existente.',
                ]);
            }
        }

        $cliente = Cliente::create([
            'razao_social' => trim((string) Arr::get($data, 'client_razao_social')),
            'nome_fantasia' => blank(Arr::get($data, 'client_nome_fantasia')) ? null : trim((string) Arr::get($data, 'client_nome_fantasia')),
            'cnpj' => $cnpj === '' ? null : $cnpj,
            'id_categoria_segmento' => blank(Arr::get($data, 'client_segmento_id')) ? null : (int) Arr::get($data, 'client_segmento_id'),
            'id_status_cliente' => blank(Arr::get($data, 'client_status_id')) ? null : (int) Arr::get($data, 'client_status_id'),
            'nome_completo' => blank(Arr::get($data, 'client_nome_completo')) ? null : trim((string) Arr::get($data, 'client_nome_completo')),
            'cargo' => blank(Arr::get($data, 'client_cargo')) ? null : trim((string) Arr::get($data, 'client_cargo')),
            'email' => blank(Arr::get($data, 'client_email')) ? null : trim((string) Arr::get($data, 'client_email')),
            'telefone' => blank(Arr::get($data, 'client_telefone')) ? null : trim((string) Arr::get($data, 'client_telefone')),
            'cidade' => blank(Arr::get($data, 'client_cidade')) ? null : trim((string) Arr::get($data, 'client_cidade')),
            'uf' => blank(Arr::get($data, 'client_uf')) ? null : trim((string) Arr::get($data, 'client_uf')),
            'observacao' => blank(Arr::get($data, 'client_observacao')) ? null : trim((string) Arr::get($data, 'client_observacao')),
        ]);

        return $cliente->fresh(['categoriaSegmento', 'statusCliente']);
    }
}
