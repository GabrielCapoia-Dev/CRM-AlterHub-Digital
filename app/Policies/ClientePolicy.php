<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarClientes->value);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarClientes->value)
            && $this->withinScope($user, $cliente);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarClientes->value);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarClientes->value)
            && $this->withinScope($user, $cliente);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirClientes->value)
            && $this->withinScope($user, $cliente);
    }

    public function deleteAny(User $user): bool
    {
        return ! $user->hasRole(RolesEnum::Vendedor->value)
            && $user->hasPermissionTo(PermissoesEnum::ExcluirClientes->value);
    }

    protected function withinScope(User $user, Cliente $cliente): bool
    {
        return ! $user->hasRole(RolesEnum::Vendedor->value)
            || ! $cliente->exists
            || (int) $cliente->vendedor_id === (int) $user->id;
    }
}
