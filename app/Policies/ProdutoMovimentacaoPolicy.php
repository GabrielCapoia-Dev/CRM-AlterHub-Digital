<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\ProdutoMovimentacao;

class ProdutoMovimentacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value);
    }

    public function view(User $user, ProdutoMovimentacao $produtoMovimentacao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value);
    }

    public function update(User $user, ProdutoMovimentacao $produtoMovimentacao): bool
    {
        return false;
    }

    public function delete(User $user, ProdutoMovimentacao $produtoMovimentacao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirProdutosCRM->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirProdutosCRM->value);
    }
}
