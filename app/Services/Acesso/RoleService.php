<?php

namespace App\Services\Acesso;

use App\Enum\RolesEnum;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;

class RoleService
{
    public function ehSuperAdmin(?User $user): bool
    {
        return $user?->hasRole(RolesEnum::SuperAdmin->value) ?? false;
    }

    public function ehAdmin(?User $user): bool
    {
        return $user?->hasRole(RolesEnum::Admin->value) ?? false;
    }

    public function ehVendedor(?User $user): bool
    {
        return $user?->hasRole(RolesEnum::Vendedor->value) ?? false;
    }

    public function ehGestor(?User $user): bool
    {
        return $user?->hasRole(RolesEnum::Gestor->value) ?? false;
    }

    /** Gestor e administradores podem escolher o responsavel pela venda. */
    public function podeEscolherVendedor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->ehSuperAdmin($user)
            || $this->ehAdmin($user)
            || $this->ehGestor($user);
    }

    public function roleEhProtegida(Role $record): bool
    {
        return in_array($record->name, [
            RolesEnum::Admin->value,
            RolesEnum::SuperAdmin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Vendedor->value,
            RolesEnum::Usuario->value,
        ], true);
    }

    public function roleEhBloqueadaParaEdicao(Role $record, string $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return in_array($record->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Vendedor->value,
        ], true);
    }

    public function roleEhBloqueadaParaExclusao(Role $record): bool
    {
        return in_array($record->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Vendedor->value,
            RolesEnum::Usuario->value,
        ], true);
    }

    public function roleEhSelecionavelEmMassa(Role $record): bool
    {
        return ! $this->roleEhProtegida($record);
    }
}
