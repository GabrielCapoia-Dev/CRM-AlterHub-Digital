<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarUsuarios->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarUsuarios->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarUsuarios->value);
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::EditarUsuarios->value)) {
            return false;
        }

        if ($model->hasRole(RolesEnum::SuperAdmin->value)) {
            return $user->hasRole(RolesEnum::SuperAdmin->value)
                && (int) $user->id === (int) $model->id;
        }

        if ($model->hasRole(RolesEnum::Admin->value)) {
            return $user->hasRole(RolesEnum::SuperAdmin->value)
                || $user->hasPermissionTo(PermissoesEnum::EditarNivelDeAcessoAdmin->value);
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::ExcluirUsuarios->value)
            || (int) $user->id === (int) $model->id
            || $model->hasRole(RolesEnum::SuperAdmin->value)) {
            return false;
        }

        return ! $model->hasRole(RolesEnum::Admin->value)
            || $user->hasRole(RolesEnum::SuperAdmin->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(RolesEnum::SuperAdmin->value)
            && $user->hasPermissionTo(PermissoesEnum::ExcluirUsuarios->value);
    }
}
