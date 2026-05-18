<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OportunidadeTarefa;

class OportunidadeTarefaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarTarefasDeOportunidade->value);
    }

    public function view(User $user, OportunidadeTarefa $oportunidadeTarefa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarTarefasDeOportunidade->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarTarefasDeOportunidade->value);
    }

    public function update(User $user, OportunidadeTarefa $oportunidadeTarefa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarTarefasDeOportunidade->value);
    }

    public function delete(User $user, OportunidadeTarefa $oportunidadeTarefa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirTarefasDeOportunidade->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirTarefasDeOportunidade->value);
    }
}
