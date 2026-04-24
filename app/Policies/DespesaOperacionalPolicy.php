<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\DespesaOperacional;

class DespesaOperacionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarDespesasOperacionais->value);
    }

    public function view(User $user, DespesaOperacional $despesaOperacional): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarDespesasOperacionais->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarDespesasOperacionais->value);
    }

    public function update(User $user, DespesaOperacional $despesaOperacional): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarDespesasOperacionais->value);
    }

    public function delete(User $user, DespesaOperacional $despesaOperacional): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirDespesasOperacionais->value);
    }
}
