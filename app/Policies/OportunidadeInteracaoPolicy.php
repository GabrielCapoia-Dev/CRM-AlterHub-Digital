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
        return $user->hasPermissionTo(PermissoesEnum::ListarInteracoesDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeInteracao);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarInteracoesDeOportunidade->value);
    }

    public function update(User $user, OportunidadeInteracao $oportunidadeInteracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarInteracoesDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeInteracao);
    }

    public function delete(User $user, OportunidadeInteracao $oportunidadeInteracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirInteracoesDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeInteracao);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function withinScope(User $user, OportunidadeInteracao $record): bool
    {
        return ! $record->exists
            || ! $record->oportunidade_id
            || $user->can('view', $record->oportunidade);
    }
}
