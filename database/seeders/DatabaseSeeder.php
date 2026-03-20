<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $senhaAdmin = env('SUPER_ADMIN_PASSWORD', 'password');

        // Limpar cache de permissões
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Criar a Role "Super Admin"
        $this->command->info('Criando role Super Admin...');
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->command->line('✔ Role Super Admin criada');

        // 2. Criar permissões
        $this->command->info('Criando permissões...');
        $permissoes = [
            'Acessar Painel'
        ];

        foreach ($permissoes as $nome) {
            $permission = Permission::firstOrCreate(['name' => $nome]);
            $this->command->line("✔ Permissão criada: {$nome}");
        }

        // 3. Vincular permissões à role Super Admin
        $this->command->info('Vinculando permissões à role Super Admin...');
        $superAdminRole->givePermissionTo($permissoes);
        $this->command->line('✔ Permissões vinculadas com sucesso');

        // 4. Criar usuário admin
        $this->command->info('Criando usuário admin...');
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt($senhaAdmin),
                'email_verified_at' => now(),
            ]
        );

        // 5. Atribuir role ao usuário
        $this->command->info('Atribuindo role Super Admin ao usuário admin...');
        $adminUser->assignRole('Super Admin');
        $this->command->line('✔ Usuário admin criado e role atribuída');

        $this->command->info('✅ Seeder executado com sucesso!');
        $this->command->newLine();
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Email', 'admin@admin.com'],
                ['Senha', $senhaAdmin],
                ['Role', 'Super Admin'],
                ['Permissões', 'Acessar Painel'],
            ]
        );
    }
}
