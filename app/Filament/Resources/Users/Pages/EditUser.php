<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enum\RolesEnum;
use App\Filament\Resources\Users\UserResource;
use App\Models\Acesso\User;
use App\Services\Acesso\UserService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeSave(): void
    {
        if (! array_key_exists('role', $this->data) || empty($this->data['role'])) {
            return;
        }

        /** @var User $actor */
        $actor = Auth::user();
        app(UserService::class)->validarRoleAtribuivel($actor, $this->data['role']);
    }

    protected function afterSave(): void
    {
        /** @var User $actor */
        $actor = Auth::user();
        /** @var User $record */
        $record = $this->record;
        $service = app(UserService::class);

        if (array_key_exists('role', $this->data) && ! empty($this->data['role'])) {
            $service->sincronizarRole($record, $actor, $this->data['role']);
        }

        if (array_key_exists('cliente_ids', $this->data) || ! $record->hasRole(RolesEnum::Vendedor->value)) {
            $service->sincronizarClientesDoVendedor(
                $record,
                (array) ($this->data['cliente_ids'] ?? []),
            );
        }

        $service->sincronizarPermissoesDiretas(
            $record,
            $actor,
            (bool) ($this->data['usar_permissoes_extras'] ?? false),
            (array) ($this->data['permissions'] ?? []),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
