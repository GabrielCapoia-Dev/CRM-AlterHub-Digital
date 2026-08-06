<?php

namespace Tests\Feature\Database;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EssentialSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['BOOTSTRAP_ADMIN_NAME', 'BOOTSTRAP_ADMIN_EMAIL', 'BOOTSTRAP_ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    public function test_essential_seed_creates_permissions_without_a_default_user(): void
    {
        $this->setBootstrapEnvironment('', '', '');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertTrue(Role::query()->where('name', RolesEnum::SuperAdmin->value)->exists());
        $this->assertTrue(Role::query()->where('name', RolesEnum::Gestor->value)->exists());
        $this->assertTrue(Role::query()->where('name', RolesEnum::Estoquista->value)->exists());
    }

    public function test_bootstrap_admin_requires_all_fields_and_a_strong_password(): void
    {
        $this->setBootstrapEnvironment('Administrador', 'admin@example.com', 'fraca');

        $this->expectException(RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_bootstrap_admin_is_created_once_from_explicit_secure_values(): void
    {
        $this->setBootstrapEnvironment('Administrador inicial', 'admin-inicial@example.com', 'SenhaMuitoForte#2026');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin-inicial@example.com')->firstOrFail();
        $this->assertSame(1, User::query()->count());
        $this->assertTrue($admin->email_approved);
        $this->assertTrue($admin->hasRole(RolesEnum::SuperAdmin->value));
    }

    private function setBootstrapEnvironment(string $name, string $email, string $password): void
    {
        foreach ([
            'BOOTSTRAP_ADMIN_NAME' => $name,
            'BOOTSTRAP_ADMIN_EMAIL' => $email,
            'BOOTSTRAP_ADMIN_PASSWORD' => $password,
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
