<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Romaneio;

class RomaneioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarRomaneios->value);
    }

    public function view(User $user, Romaneio $romaneio): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::GerarRomaneio->value);
    }

    public function reprint(User $user, Romaneio $romaneio): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ReimprimirRomaneio->value);
    }

    public function print(User $user, Romaneio $romaneio): bool
    {
        if ($this->reprint($user, $romaneio)) {
            return true;
        }

        return (int) $romaneio->user_id === (int) $user->id
            && $user->hasPermissionTo(PermissoesEnum::GerarRomaneio->value)
            && ! $romaneio->historicos()
                ->whereIn('evento', ['pdf_gerado', 'pdf_reimpresso'])
                ->exists();
    }

    public function cancel(User $user, Romaneio $romaneio): bool
    {
        return $romaneio->isGerado()
            && $user->hasPermissionTo(PermissoesEnum::CancelarRomaneio->value);
    }

    public function dispatch(User $user, Romaneio $romaneio): bool
    {
        return $romaneio->isGerado()
            && $user->hasPermissionTo(PermissoesEnum::GerarRomaneio->value);
    }

    public function delete(User $user, Romaneio $romaneio): bool
    {
        return false;
    }
}
