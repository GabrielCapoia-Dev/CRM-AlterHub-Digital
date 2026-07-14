<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OrdemProducao;

class OrdemProducaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarOrdensProducao->value);
    }

    public function view(User $user, OrdemProducao $ordemProducao): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarOrdensProducao->value);
    }

    public function update(User $user, OrdemProducao $ordemProducao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarOrdensProducao->value);
    }

    public function delete(User $user, OrdemProducao $ordemProducao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirOrdensProducao->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirOrdensProducao->value);
    }
}
