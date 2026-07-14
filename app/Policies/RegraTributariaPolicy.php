<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\RegraTributaria;

class RegraTributariaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarRegrasTributarias->value);
    }

    public function view(User $user, RegraTributaria $regraTributaria): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarRegrasTributarias->value);
    }

    public function update(User $user, RegraTributaria $regraTributaria): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarRegrasTributarias->value);
    }

    public function delete(User $user, RegraTributaria $regraTributaria): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirRegrasTributarias->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirRegrasTributarias->value);
    }
}
