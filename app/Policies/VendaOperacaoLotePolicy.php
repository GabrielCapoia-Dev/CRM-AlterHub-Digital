<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\VendaOperacaoLote;

class VendaOperacaoLotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CadastrarLotesValidades->value);
    }

    public function view(User $user, VendaOperacaoLote $lote): bool
    {
        $pedido = $lote->vendaOperacao?->vendaOperacaoPedido;

        return $pedido !== null && $user->can('view', $pedido);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::CadastrarLotesValidades->value);
    }

    public function update(User $user, VendaOperacaoLote $lote): bool
    {
        $pedido = $lote->vendaOperacao?->vendaOperacaoPedido;

        return $pedido !== null && $user->can('manageLots', $pedido);
    }

    public function delete(User $user, VendaOperacaoLote $lote): bool
    {
        return $this->update($user, $lote);
    }
}
