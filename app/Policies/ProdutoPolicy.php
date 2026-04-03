<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Produto;

class ProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value);
    }

    public function view(User $user, Produto $produto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarProdutosCRM->value);
    }

    public function update(User $user, Produto $produto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value);
    }

    public function delete(User $user, Produto $produto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirProdutosCRM->value);
    }
}
