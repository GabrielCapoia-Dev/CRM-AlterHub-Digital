<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
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
        return $user->hasPermissionTo(PermissoesEnum::ListarOportunidades->value)
            && $this->withinScope($user, $oportunidade);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarOportunidades->value);
    }

    public function update(User $user, Oportunidade $oportunidade): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarOportunidades->value)
            && $this->withinScope($user, $oportunidade)
            && $oportunidade->canEditCommercially();
    }

    public function delete(User $user, Oportunidade $oportunidade): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirOportunidades->value)
            && $this->withinScope($user, $oportunidade)
            && ! $oportunidade->isConverted();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reopen(User $user, Oportunidade $oportunidade): bool
    {
        return $oportunidade->isLost()
            && $this->canViewAll($user)
            && $user->hasPermissionTo(PermissoesEnum::EditarOportunidades->value);
    }

    protected function withinScope(User $user, Oportunidade $oportunidade): bool
    {
        return $this->canViewAll($user)
            || ! $oportunidade->exists
            || (int) $oportunidade->user_id === (int) $user->id;
    }

    protected function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
        ])
            || $user->hasPermissionTo(PermissoesEnum::AprovarDesconto->value);
    }
}
