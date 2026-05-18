<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OportunidadeInteracao;

class OportunidadeInteracaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInteracoesDeOportunidade->value);
    }

    public function view(User $user, OportunidadeInteracao $oportunidadeInteracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInteracoesDeOportunidade->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarInteracoesDeOportunidade->value);
    }

    public function update(User $user, OportunidadeInteracao $oportunidadeInteracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarInteracoesDeOportunidade->value);
    }

    public function delete(User $user, OportunidadeInteracao $oportunidadeInteracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirInteracoesDeOportunidade->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirInteracoesDeOportunidade->value);
    }
}
