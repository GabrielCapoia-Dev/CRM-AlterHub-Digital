<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Remessa;

class RemessaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value);
    }

    public function view(User $user, Remessa $remessa): bool
    {
        return $user->can('view', $remessa->pedido);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value);
    }

    public function update(User $user, Remessa $remessa): bool
    {
        return $user->can('manageShipments', $remessa->pedido);
    }

    public function delete(User $user, Remessa $remessa): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
