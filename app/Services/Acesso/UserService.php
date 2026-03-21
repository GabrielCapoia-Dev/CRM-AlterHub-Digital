<?php

namespace App\Services\Acesso;

use App\Models\Acesso\User;
use App\RolesEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class UserService
{
    protected User $user;
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        /** @var \App\Models\Acesso\User */
        $this->user = Auth::user();
        $this->roleService = $roleService;
    }

    // =========================================================================
    // Regras de formulário
    // =========================================================================

    public function opcoesDeRoles(Builder $base, ?User $user): Builder
    {
        return $this->roleService->ehSuperAdmin($user)
            ? $base
            : $base->where('name', '!=', RolesEnum::Admin->value);
    }

    public function desabilitarCampoRole(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create' || ! $record) return false;
        if ($record->hasRole(RolesEnum::Admin->value) && $user?->id == 1) return false;
        if ($record->hasRole(RolesEnum::Admin->value)) return true;
        if ($user && $record->id === $user->id) return true;
        return false;
    }

    public function podeVerToggleAprovacaoEmail(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create') return true;
        if (! $user || ! $this->roleService->ehSuperAdmin($user)) return false;
        if ($record && ($record->hasRole(RolesEnum::Admin->value) || $record->id === $user->id)) return false;
        return true;
    }

    public function desabilitarToggleAprovacaoEmail(?User $user, ?User $record): bool
    {
        return $user && $record && $user->id === $record->id;
    }

    // =========================================================================
    // Regras de tabela
    // =========================================================================

    public function podeSelecionarRegistro(?User $user, User $record): bool
    {
        if ($record->hasRole(RolesEnum::Admin->value)) return false;
        return true;
    }

    public function podeDeletar(?User $user, User $record): bool
    {
        if (! $user) return false;
        if ($record->id === 1) return false;
        if ($record->id === $user->id) return false;
        return $this->roleService->ehSuperAdmin($user);
    }

    public function podeDeletarEmLote(?User $user, iterable $records): bool
    {
        if (! $this->roleService->ehSuperAdmin($user)) return false;
        foreach ($records as $record) {
            if ($record instanceof User && $record->hasRole(RolesEnum::Admin->value)) return false;
        }
        return true;
    }

    // =========================================================================
    // Helpers de checkboxes de permissão
    // =========================================================================

    public function checkboxesPermissoesComEstado(User $record, Get $get, User $userLogado): array
    {
        $busca = strtolower($get('buscar_permissao') ?? '');

        $permissoesDisponiveis = $this->roleService->ehAdmin($userLogado)
            ? Permission::query()
            : Permission::whereIn('name', $userLogado->getAllPermissions()->pluck('name'));

        $todas = $permissoesDisponiveis
            ->orderBy('name')
            ->when($busca, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $this->roleService->ehAdmin($userLogado), fn($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $permissoesDaRole = $record->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->toArray();

        $permissoesDiretas = $record->getDirectPermissions()->pluck('name')->toArray();
        $porGrupo = $todas->groupBy(fn($p) => explode(' ', $p->name)[0]);
        $schema = [];

        foreach ($porGrupo as $grupo => $permissoes) {
            $filtradas = $permissoes->when(
                $busca,
                fn($collection) => $collection->filter(
                    fn($perm) => str_contains(strtolower($perm->name), $busca)
                )
            );

            if ($filtradas->isEmpty()) continue;

            $permissoesDoGrupoNaRole = collect($filtradas)
                ->filter(fn($p) => in_array($p->name, $permissoesDaRole))
                ->pluck('name')
                ->toArray();

            $schema[] = CheckboxList::make("permissions_{$grupo}")
                ->label($grupo)
                ->options($filtradas->pluck('name', 'name')->toArray())
                ->columns(3)
                ->helperText(! empty($permissoesDoGrupoNaRole) ? '🔒 Herança da role: ' . implode(', ', $permissoesDoGrupoNaRole) : '')
                ->default(
                    collect($permissoesDaRole)
                        ->merge($permissoesDiretas)
                        ->intersect($filtradas->pluck('name'))
                        ->values()
                        ->toArray()
                )
                ->dehydrated(true);
        }

        return $schema;
    }

    public function checkboxesPermissoesEmMassa(Get $get, User $userLogado): array
    {
        $busca = strtolower($get('buscar_permissao') ?? '');

        $permissoesDoUsuario = $this->roleService->ehAdmin($userLogado)
            ? Permission::query()
            : Permission::whereIn('name', $userLogado->getAllPermissions()->pluck('name'));

        $todas = $permissoesDoUsuario
            ->orderBy('name')
            ->when($busca, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $this->roleService->ehAdmin($userLogado), fn($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $porGrupo = $todas->groupBy(fn($p) => explode(' ', $p->name)[0]);
        $schema = [];

        foreach ($porGrupo as $grupo => $permissoes) {
            $schema[] = CheckboxList::make("permissions_{$grupo}")
                ->label($grupo)
                ->options($permissoes->pluck('name', 'name')->toArray())
                ->columns(3);
        }

        return $schema;
    }
}