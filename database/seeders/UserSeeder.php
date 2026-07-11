<?php

namespace Database\Seeders;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $usuarios = [
            // Admins
            [
                'name'               => 'Admin Um',
                'email'              => 'admin1@unibiotech.com',
                'role'               => RolesEnum::Admin,
            ],
            [
                'name'               => 'Admin Dois',
                'email'              => 'admin2@unibiotech.com',
                'role'               => RolesEnum::Admin,
            ],

            // Vendedores
            [
                'name'               => 'Vendedor Um',
                'email'              => 'vendedor1@unibiotech.com',
                'role'               => RolesEnum::Vendedor,
            ],
            [
                'name'               => 'Vendedor Dois',
                'email'              => 'vendedor2@unibiotech.com',
                'role'               => RolesEnum::Vendedor,
            ],

            // Usuários
            [
                'name'               => 'Usuário Um',
                'email'              => 'usuario1@unibiotech.com',
                'role'               => RolesEnum::Usuario,
            ],
            [
                'name'               => 'Usuário Dois',
                'email'              => 'usuario2@unibiotech.com',
                'role'               => RolesEnum::Usuario,
            ],
            [
                'name'               => 'Usuário Três',
                'email'              => 'usuario3@unibiotech.com',
                'role'               => RolesEnum::Usuario,
            ],
            [
                'name'               => 'Usuário Quatro',
                'email'              => 'usuario4@unibiotech.com',
                'role'               => RolesEnum::Usuario,
            ],
            [
                'name'               => 'Usuário Cinco',
                'email'              => 'usuario5@unibiotech.com',
                'role'               => RolesEnum::Usuario,
            ],
        ];

        $senha = env('SEED_USER_PASSWORD', 'password');

        foreach ($usuarios as $dados) {
            $user = User::firstOrCreate(
                ['email' => $dados['email']],
                [
                    'uuid'               => \Illuminate\Support\Str::uuid(),
                    'name'               => $dados['name'],
                    'password'           => bcrypt($senha),
                    'email_verified_at'  => now(),
                    'email_approved'     => true,
                ]
            );
            $user->syncRoles([$dados['role']->value]);

            $this->command->line("✔ {$dados['role']->value} — {$dados['name']}");
        }

        $this->command->info('✅ Usuários de teste criados!');
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Senha'],
            collect($usuarios)->map(fn($u) => [
                $u['role']->value,
                $u['email'],
                $senha,
            ])->toArray()
        );
    }
}
