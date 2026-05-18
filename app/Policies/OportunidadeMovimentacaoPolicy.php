<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OportunidadeMovimentacao;

class OportunidadeMovimentacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarMovimentacoesDeOportunidade->value);
    }

    public function view(User $user, OportunidadeMovimentacao $oportunidadeMovimentacao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarMovimentacoesDeOportunidade->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OportunidadeMovimentacao $oportunidadeMovimentacao): bool
    {
        return false;
    }

    public function delete(User $user, OportunidadeMovimentacao $oportunidadeMovimentacao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirMovimentacoesDeOportunidade->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirMovimentacoesDeOportunidade->value);
    }
}
