<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Transportadora;

class TransportadoraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarTransportadoras->value);
    }

    public function view(User $user, Transportadora $transportadora): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarTransportadoras->value);
    }

    public function update(User $user, Transportadora $transportadora): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarTransportadoras->value);
    }

    public function delete(User $user, Transportadora $transportadora): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirTransportadoras->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirTransportadoras->value);
    }
}
