<?php

namespace Database\Seeders;

use App\Models\Acesso\User;
use App\Enum\RolesEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $senhaAdmin = env('SUPER_ADMIN_PASSWORD', 'password');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Criar roles
        $this->command->info('Criando roles...');
        foreach (RolesEnum::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value]);
            $this->command->line("✔ Role criada: {$role->value}");
        }

        // 2. Criar usuário Super Admin
        $this->command->info('Criando usuário Super Admin...');
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt($senhaAdmin),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        // 3. Atribuir role
        $this->command->info('Atribuindo role Super Admin...');
        $adminUser->assignRole(RolesEnum::SuperAdmin->value);
        $this->command->line('✔ Role atribuída com sucesso');

        $this->command->info('✅ Seeder executado com sucesso!');
        $this->command->newLine();
        $this->command->table(
            ['Campo', 'Valor'],
            [
                ['Email', 'admin@admin.com'],
                ['Senha', $senhaAdmin],
                ['Role', RolesEnum::SuperAdmin->value],
                ['UUID', 'gerado automaticamente'],
            ]
        );
    }
}