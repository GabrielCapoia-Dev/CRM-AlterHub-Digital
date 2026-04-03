<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OportunidadeProduto;

class OportunidadeProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosDaOportunidade->value);
    }

    public function view(User $user, OportunidadeProduto $oportunidadeProduto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosDaOportunidade->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarProdutosDaOportunidade->value);
    }

    public function update(User $user, OportunidadeProduto $oportunidadeProduto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarProdutosDaOportunidade->value);
    }

    public function delete(User $user, OportunidadeProduto $oportunidadeProduto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirProdutosDaOportunidade->value);
    }
}
