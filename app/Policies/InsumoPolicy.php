<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Produtos\Insumo;

class InsumoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInsumos->value);
    }

    public function view(User $user, Insumo $insumo): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInsumos->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarInsumos->value);
    }

    public function update(User $user, Insumo $insumo): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarInsumos->value);
    }

    public function delete(User $user, Insumo $insumo): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirInsumos->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirInsumos->value);
    }
}
