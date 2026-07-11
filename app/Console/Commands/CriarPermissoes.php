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

    protected $description = 'Cria permissoes e vincula conjuntos padrao por role';

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

        $allPermissions = collect(PermissoesEnum::cases())
            ->map(fn (PermissoesEnum $permissao): string => $permissao->value)
            ->all();

        $superAdminRole = Role::firstOrCreate([
            'name' => RolesEnum::SuperAdmin->value,
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissions);
        $this->info('Permissoes sincronizadas com Super Admin.');

        $adminRole = Role::firstOrCreate([
            'name' => RolesEnum::Admin->value,
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions($this->adminPermissions());
        $this->info('Permissoes sincronizadas com Admin.');

        $vendedorRole = Role::firstOrCreate([
            'name' => RolesEnum::Vendedor->value,
            'guard_name' => 'web',
        ]);
        $vendedorRole->syncPermissions($this->vendedorPermissions());
        $this->info('Permissoes sincronizadas com Vendedor.');

        Role::firstOrCreate([
            'name' => RolesEnum::Usuario->value,
            'guard_name' => 'web',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function vendedorPermissions(): array
    {
        return [
            PermissoesEnum::ListarClientes->value,
            PermissoesEnum::CriarClientes->value,
            PermissoesEnum::EditarClientes->value,
            PermissoesEnum::ListarProdutosCRM->value,
            PermissoesEnum::ListarVendasOperacao->value,
            PermissoesEnum::CriarVendasOperacao->value,
        ];
    }

    /**
     * @return list<string>
     */
    protected function adminPermissions(): array
    {
        return collect(PermissoesEnum::cases())
            ->map(fn (PermissoesEnum $permissao): string => $permissao->value)
            ->reject(fn (string $permission): bool => in_array($permission, [
                PermissoesEnum::AplicarPermissoes->value,
                PermissoesEnum::EditarNivelDeAcessoAdmin->value,
            ], true))
            ->values()
            ->all();
    }
}
