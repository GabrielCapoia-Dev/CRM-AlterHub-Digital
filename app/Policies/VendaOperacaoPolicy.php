<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\VendaOperacao;
use App\Services\Operacao\VendaOperacaoService;

class VendaOperacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value);
    }

    public function view(User $user, VendaOperacao $vendaOperacao): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $vendaOperacao);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarVendasOperacao->value);
    }

    public function update(User $user, VendaOperacao $vendaOperacao): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $vendaOperacao);
    }

    public function delete(User $user, VendaOperacao $vendaOperacao): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $vendaOperacao);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value);
    }

    protected function withinScope(User $user, VendaOperacao $vendaOperacao): bool
    {
        if (app(VendaOperacaoService::class)->podeVerTodasVendas($user)) {
            return true;
        }

        if (! $vendaOperacao->exists || $vendaOperacao->user_id === null) {
            return true;
        }

        return (int) $vendaOperacao->user_id === (int) $user->id;
    }
}
