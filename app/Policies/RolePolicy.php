<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
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
        return $user->hasPermissionTo(PermissoesEnum::CriarNiveisDeAcesso->value)
            && $user->hasPermissionTo(PermissoesEnum::AplicarPermissoes->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $role->name !== RolesEnum::SuperAdmin->value
            && $user->hasPermissionTo(PermissoesEnum::EditarNiveisDeAcesso->value)
            && $user->hasPermissionTo(PermissoesEnum::AplicarPermissoes->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return ! in_array($role->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Vendedor->value,
            RolesEnum::Estoquista->value,
            RolesEnum::Usuario->value,
        ], true)
            && $user->hasPermissionTo(PermissoesEnum::ExcluirNiveisDeAcesso->value)
            && $user->hasPermissionTo(PermissoesEnum::AplicarPermissoes->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirNiveisDeAcesso->value)
            && $user->hasPermissionTo(PermissoesEnum::AplicarPermissoes->value);
    }
}
