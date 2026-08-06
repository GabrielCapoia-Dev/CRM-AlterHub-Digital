<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Acesso\User;
use App\Services\Acesso\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeCreate(): void
    {
        /** @var User $actor */
        $actor = Auth::user();
        app(UserService::class)->validarRoleAtribuivel($actor, $this->data['role'] ?? null);
    }

    protected function afterCreate(): void
    {
        /** @var User $actor */
        $actor = Auth::user();
        /** @var User $record */
        $record = $this->record;
        $service = app(UserService::class);

        $service->sincronizarRole($record, $actor, $this->data['role'] ?? null);
        $service->sincronizarPermissoesDiretas(
            $record,
            $actor,
            (bool) ($this->data['usar_permissoes_extras'] ?? false),
            (array) ($this->data['permissions'] ?? []),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
