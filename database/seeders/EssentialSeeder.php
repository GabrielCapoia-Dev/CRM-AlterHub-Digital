<?php

namespace Database\Seeders;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class EssentialSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Artisan::call('permissoes:criar');

        $name = trim((string) env('BOOTSTRAP_ADMIN_NAME', ''));
        $email = trim((string) env('BOOTSTRAP_ADMIN_EMAIL', ''));
        $password = (string) env('BOOTSTRAP_ADMIN_PASSWORD', '');
        $provided = collect([$name, $email, $password])->filter(fn (string $value): bool => $value !== '')->count();

        if ($provided === 0) {
            return;
        }

        if ($provided !== 3) {
            throw new RuntimeException('Informe BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_EMAIL e BOOTSTRAP_ADMIN_PASSWORD em conjunto.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('BOOTSTRAP_ADMIN_EMAIL precisa ser um endereco de e-mail valido.');
        }

        if (! $this->isStrongPassword($password)) {
            throw new RuntimeException('BOOTSTRAP_ADMIN_PASSWORD precisa ter ao menos 16 caracteres, com maiuscula, minuscula, numero e simbolo.');
        }

        $admin = User::query()->firstOrCreate(
            ['email' => Str::lower($email)],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
                'email_approved' => true,
            ],
        );

        $admin->assignRole(RolesEnum::SuperAdmin->value);
        $this->command?->info("Administrador inicial disponivel: {$admin->email}");
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 16
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}
