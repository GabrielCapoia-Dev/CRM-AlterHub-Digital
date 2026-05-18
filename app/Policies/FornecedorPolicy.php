<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Empresas\Fornecedor;

class FornecedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarFornecedores->value);
    }

    public function view(User $user, Fornecedor $fornecedor): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarFornecedores->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarFornecedores->value);
    }

    public function update(User $user, Fornecedor $fornecedor): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarFornecedores->value);
    }

    public function delete(User $user, Fornecedor $fornecedor): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirFornecedores->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirFornecedores->value);
    }
}
