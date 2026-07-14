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

        return $this->withinScope($user, $pedido) && $pedido->canEditCommercially();
    }

    public function delete(User $user, VendaOperacaoPedido $pedido): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
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

    public function confirm(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->withinScope($user, $pedido)
            && $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value)
            && $pedido->status === VendaOperacaoPedido::STATUS_RASCUNHO;
    }

    public function cancel(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->withinScope($user, $pedido)
            && $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value)
            && in_array($pedido->status, [
                VendaOperacaoPedido::STATUS_RASCUNHO,
                VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO,
                VendaOperacaoPedido::STATUS_CONFIRMADA,
                VendaOperacaoPedido::STATUS_RECUSADA,
            ], true)
            && ! $pedido->hasDispatchedItems();
    }

    public function reopen(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->podeGerenciarTodas($user)
            && in_array($pedido->status, [
                VendaOperacaoPedido::STATUS_CONFIRMADA,
                VendaOperacaoPedido::STATUS_CANCELADA,
                VendaOperacaoPedido::STATUS_RECUSADA,
            ], true)
            && ! $pedido->hasDispatchedItems();
    }

    public function manageShipments(User $user, VendaOperacaoPedido $pedido): bool
    {
        return $this->withinScope($user, $pedido)
            && $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value);
    }

    protected function withinScope(User $user, VendaOperacaoPedido $pedido): bool
    {
        if (app(VendaOperacaoService::class)->podeVerTodasVendas($user)) {
            return true;
        }

        if (! $pedido->exists) {
            return true;
        }

        if ($pedido->user_id === null) {
            return false;
        }

        return (int) $pedido->user_id === (int) $user->id;
    }

    protected function podeGerenciarTodas(User $user): bool
    {
        return app(VendaOperacaoService::class)->podeVerTodasVendas($user)
            && $user->hasPermissionTo(PermissoesEnum::EditarVendasOperacao->value);
    }
}
