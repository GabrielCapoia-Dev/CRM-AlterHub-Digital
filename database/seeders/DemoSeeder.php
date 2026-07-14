<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder e bloqueado em producao.');
        }

        if (strlen((string) env('DEMO_USER_PASSWORD', '')) < 16) {
            throw new RuntimeException('Defina DEMO_USER_PASSWORD com ao menos 16 caracteres antes de criar dados demonstrativos.');
        }

        $this->call([
            EssentialSeeder::class,
            UserSeeder::class,
            FornecedorSeeder::class,
            ClienteSeeder::class,
            InsumoSeeder::class,
            ProdutoSeeder::class,
            CrmSeeder::class,
        ]);
    }
}
