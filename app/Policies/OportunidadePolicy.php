<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Oportunidade;

class OportunidadePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarOportunidades->value);
    }

    public function view(User $user, Oportunidade $oportunidade): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarOportunidades->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarOportunidades->value);
    }

    public function update(User $user, Oportunidade $oportunidade): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarOportunidades->value);
    }

    public function delete(User $user, Oportunidade $oportunidade): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirOportunidades->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirOportunidades->value);
    }
}
