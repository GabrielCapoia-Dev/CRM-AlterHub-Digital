<?php

namespace App\Services\Acesso;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    public function ehEstoquista(?User $user): bool
    {
        return $user?->hasRole(RolesEnum::Estoquista->value) ?? false;
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
            RolesEnum::Estoquista->value,
            RolesEnum::Usuario->value,
        ], true);
    }

    public function roleEhBloqueadaParaEdicao(Role $record, string $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return $record->name === RolesEnum::SuperAdmin->value;
    }

    public function nomeDaRoleEhBloqueado(Role $record): bool
    {
        return $this->roleEhProtegida($record);
    }

    public function roleEhBloqueadaParaExclusao(Role $record): bool
    {
        return in_array($record->name, [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Vendedor->value,
            RolesEnum::Estoquista->value,
            RolesEnum::Usuario->value,
        ], true);
    }

    public function roleEhSelecionavelEmMassa(Role $record): bool
    {
        return ! $this->roleEhProtegida($record);
    }

    /** @return Collection<int, string> */
    public function permissoesAtribuiveis(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $ativas = collect(PermissoesEnum::activeCases())
            ->map(fn (PermissoesEnum $permissao): string => $permissao->value);

        if ($this->ehSuperAdmin($user)) {
            return $ativas->values();
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->intersect($ativas)
            ->values();
    }

    /** @param  array<int, mixed>  $permissoes */
    public function validarPermissoesAtribuiveis(User $user, array $permissoes): array
    {
        $selecionadas = collect($permissoes)
            ->filter(fn (mixed $permissao): bool => is_string($permissao) && $permissao !== '')
            ->unique()
            ->values();
        $naoPermitidas = $selecionadas->diff($this->permissoesAtribuiveis($user));

        if ($naoPermitidas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Você não pode conceder: '.$naoPermitidas->implode(', ').'.',
            ]);
        }

        return $selecionadas->all();
    }

    /** @param  array<int, mixed>  $permissoes */
    public function sincronizarPermissoes(Role $role, User $user, array $permissoes): void
    {
        $selecionadas = collect($this->validarPermissoesAtribuiveis($user, $permissoes));
        $atribuiveis = $this->permissoesAtribuiveis($user);
        $preservadas = $role->permissions()
            ->pluck('name')
            ->diff($atribuiveis);

        $role->syncPermissions($preservadas->merge($selecionadas)->unique()->all());
    }
}
