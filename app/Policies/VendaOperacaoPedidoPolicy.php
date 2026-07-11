<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;

class VendaOperacaoPedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value);
    }

    public function view(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $pedido);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CriarVendasOperacao->value);
    }

    public function update(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $pedido);
    }

    public function delete(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value)) {
            return false;
        }

        return $this->withinScope($user, $pedido);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value);
    }

    public function approveDiscount(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (! $user->hasPermissionTo(PermissoesEnum::AprovarDesconto->value)) {
            return false;
        }

        return $pedido->isPendenteAprovacao();
    }

    public function rejectDiscount(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->approveDiscount($user, $pedido);
    }

    public function copy(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->create($user) && $this->view($user, $pedido);
    }

    protected function withinScope(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (app(VendaOperacaoService::class)->podeVerTodasVendas($user)) {
            return true;
        }

        if (! $pedido->exists || $pedido->user_id === null) {
            return true;
        }

        return (int) $pedido->user_id === (int) $user->id;
    }
}
