<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarNiveisDeAcesso->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarNiveisDeAcesso->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarNiveisDeAcesso->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarNiveisDeAcesso->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirNiveisDeAcesso->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirNiveisDeAcesso->value);
    }
}
