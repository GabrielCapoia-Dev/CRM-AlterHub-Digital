<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\InsumoMovimentacao;

class InsumoMovimentacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInsumos->value);
    }

    public function view(User $user, InsumoMovimentacao $insumoMovimentacao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarInsumos->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarInsumos->value);
    }

    public function update(User $user, InsumoMovimentacao $insumoMovimentacao): bool
    {
        return false;
    }

    public function delete(User $user, InsumoMovimentacao $insumoMovimentacao): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
