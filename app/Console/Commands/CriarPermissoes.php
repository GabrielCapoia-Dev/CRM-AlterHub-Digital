<?php

namespace App\Console\Commands;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CriarPermissoes extends Command
{
    protected $signature = 'permissoes:criar';

    protected $description = 'Cria permissoes e vincula a role Super Admin';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Criando permissoes...');

        foreach (PermissoesEnum::cases() as $permissao) {
            $permission = Permission::firstOrCreate([
                'name' => $permissao->value,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $this->line("Criada: {$permissao->value}");
            }
        }

        $superAdminRole = Role::firstOrCreate([
            'name' => RolesEnum::SuperAdmin->value,
            'guard_name' => 'web',
        ]);

        $superAdminRole->givePermissionTo(
            collect(PermissoesEnum::cases())->map(fn ($permissao) => $permissao->value)->toArray()
        );

        $this->info('Permissoes vinculadas a role Super Admin com sucesso.');

        return Command::SUCCESS;
    }
}
