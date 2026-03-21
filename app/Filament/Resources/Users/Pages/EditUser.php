<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Acesso\Role;
use App\Services\Acesso\UserService;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $this->sincronizarRole();
        $this->sincronizarPermissoes();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function sincronizarRole(): void
    {
        if (empty($this->data['role'])) return;

        $roleId = is_array($this->data['role'])
            ? $this->data['role'][0]
            : $this->data['role'];

        $role = Role::find($roleId);

        if ($role) {
            $this->record->syncRoles([$role]);
        }
    }

    private function sincronizarPermissoes(): void
    {
        if (empty($this->data['usar_permissoes_extras'])) {
            $this->record->syncPermissions([]);
            return;
        }

        $permissoesSelecionadas = collect($this->data)
            ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->unique()
            ->values();

        $permissoesDaRole = $this->record->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->toArray();

        $permissoesAtuais = $this->record->getDirectPermissions()->pluck('name');

        $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);
        $paraAdicionar = $permissoesSelecionadas->diff($permissoesAtuais)->diff($permissoesDaRole);

        if ($paraRemover->isNotEmpty()) {
            $this->record->revokePermissionTo($paraRemover->toArray());
        }

        if ($paraAdicionar->isNotEmpty()) {
            $this->record->givePermissionTo($paraAdicionar->toArray());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}