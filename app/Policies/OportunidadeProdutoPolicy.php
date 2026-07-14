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
        return $user->hasPermissionTo(PermissoesEnum::ListarProdutosDaOportunidade->value)
            && $user->can('view', $oportunidadeProduto->oportunidade);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarProdutosDaOportunidade->value);
    }

    public function update(User $user, OportunidadeProduto $oportunidadeProduto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarProdutosDaOportunidade->value)
            && $user->can('update', $oportunidadeProduto->oportunidade);
    }

    public function delete(User $user, OportunidadeProduto $oportunidadeProduto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirProdutosDaOportunidade->value)
            && $user->can('update', $oportunidadeProduto->oportunidade);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
