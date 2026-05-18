<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
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
        return $user->hasPermissionTo(PermissoesEnum::EditarUsuarios->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirUsuarios->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirUsuarios->value);
    }
}
