<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\VendaOperacaoPedido;

class VendaOperacaoPedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value);
    }

    public function view(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarVendasOperacao->value);
    }

    public function update(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value);
    }

    public function delete(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value);
    }
}
