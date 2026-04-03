<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Etapa;

class EtapaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarEtapasCRM->value);
    }

    public function view(User $user, Etapa $etapa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarEtapasCRM->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarEtapasCRM->value);
    }

    public function update(User $user, Etapa $etapa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarEtapasCRM->value);
    }

    public function delete(User $user, Etapa $etapa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirEtapasCRM->value);
    }
}
