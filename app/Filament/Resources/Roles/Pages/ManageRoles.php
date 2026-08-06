<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Role {
                    Gate::authorize('create', Role::class);
                    /** @var User $user */
                    $user = Auth::user();
                    $permissions = app(RoleService::class)->validarPermissoesAtribuiveis(
                        $user,
                        (array) ($data['permissions'] ?? []),
                    );

                    return DB::transaction(function () use ($data, $permissions): Role {
                        $role = Role::query()->create([
                            'name' => $data['name'],
                            'guard_name' => 'web',
                        ]);
                        $role->syncPermissions($permissions);

                        return $role;
                    });
                }),
        ];
    }
}
