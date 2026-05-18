<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
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
        return $user->hasPermissionTo(PermissoesEnum::ListarClientes->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarClientes->value);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarClientes->value);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirClientes->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirClientes->value);
    }
}
