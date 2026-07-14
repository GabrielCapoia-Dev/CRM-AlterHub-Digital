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
        return $user->hasPermissionTo(PermissoesEnum::ListarTarefasDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeTarefa);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarTarefasDeOportunidade->value);
    }

    public function update(User $user, OportunidadeTarefa $oportunidadeTarefa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarTarefasDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeTarefa)
            && $this->commercialEditable($oportunidadeTarefa);
    }

    public function delete(User $user, OportunidadeTarefa $oportunidadeTarefa): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirTarefasDeOportunidade->value)
            && $this->withinScope($user, $oportunidadeTarefa)
            && $this->commercialEditable($oportunidadeTarefa);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function withinScope(User $user, OportunidadeTarefa $record): bool
    {
        return ! $record->exists
            || ! $record->oportunidade_id
            || $user->can('view', $record->oportunidade);
    }

    protected function commercialEditable(OportunidadeTarefa $record): bool
    {
        return ! $record->exists
            || ! $record->oportunidade_id
            || $record->oportunidade->canEditCommercially();
    }
}
