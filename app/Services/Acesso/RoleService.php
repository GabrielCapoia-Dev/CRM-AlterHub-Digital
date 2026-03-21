<?php

namespace App\Services\Acesso;

use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use App\Enum\RolesEnum;

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

    public function roleEhProtegida(Role $record): bool
    {
        return in_array($record->name, [
            RolesEnum::Admin->value,
            RolesEnum::SuperAdmin->value,
            RolesEnum::Usuario->value,
        ]);
    }

    public function roleEhBloqueadaParaEdicao(Role $record, string $context): bool
    {
        if ($context === 'create') return false;

        return in_array($record->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
        ]);
    }

    public function roleEhBloqueadaParaExclusao(Role $record): bool
    {
        return in_array($record->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
        ]);
    }

    public function roleEhSelecionavelEmMassa(Role $record): bool
    {
        return ! $this->roleEhProtegida($record);
    }
}