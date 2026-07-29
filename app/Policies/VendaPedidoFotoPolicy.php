<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\VendaPedidoFoto;

class VendaPedidoFotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::VisualizarAnexosPedido->value);
    }

    public function view(User $user, VendaPedidoFoto $foto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::VisualizarAnexosPedido->value)
            && $user->can('view', $foto->pedido);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::AdicionarFotosPedido->value);
    }

    public function delete(User $user, VendaPedidoFoto $foto): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::RemoverFotosPedido->value)
            && $user->can('view', $foto->pedido);
    }
}
