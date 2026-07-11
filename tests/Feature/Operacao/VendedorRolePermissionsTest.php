<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendedorRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissoes_criar_syncs_vendedor_without_discount_approval(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Artisan::call('permissoes:criar');

        $vendedor = Role::findByName(RolesEnum::Vendedor->value, 'web');
        $admin = Role::findByName(RolesEnum::Admin->value, 'web');

        $this->assertTrue($vendedor->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value));
        $this->assertTrue($vendedor->hasPermissionTo(PermissoesEnum::CriarVendasOperacao->value));
        $this->assertTrue($vendedor->hasPermissionTo(PermissoesEnum::ListarClientes->value));
        $this->assertTrue($vendedor->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::AprovarDesconto->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::ExcluirVendasOperacao->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::ListarDashboardBI->value));

        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::AprovarDesconto->value));
        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::ListarVendasOperacao->value));
        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::CriarVendasOperacao->value));
    }
}
