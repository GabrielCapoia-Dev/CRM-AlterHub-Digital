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

    protected $description = 'Cria permissões e vincula à role Super Admin';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Criando permissões...');

        foreach (PermissoesEnum::cases() as $permissao) {
            $permission = Permission::firstOrCreate(['name' => $permissao->value, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $this->line("✔ Criada: {$permissao->value}");
            }
        }

        $superAdminRole = Role::where('name', RolesEnum::SuperAdmin->value)->first();

        if (! $superAdminRole) {
            $this->error('Role Super Admin não encontrada.');
            return Command::FAILURE;
        }

        $superAdminRole->givePermissionTo(
            collect(PermissoesEnum::cases())->map(fn($p) => $p->value)->toArray()
        );

        $this->info('Permissões vinculadas à role Super Admin com sucesso ✅');

        return Command::SUCCESS;
    }
}