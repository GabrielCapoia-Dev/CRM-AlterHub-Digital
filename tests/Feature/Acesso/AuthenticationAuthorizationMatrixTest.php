<?php

namespace Tests\Feature\Acesso;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Models\VendaOperacaoPedido;
use App\Policies\InsumoPolicy;
use App\Policies\OportunidadePolicy;
use App\Policies\ProdutoMovimentacaoPolicy;
use App\Policies\ProdutoPolicy;
use App\Policies\UserPolicy;
use App\Policies\VendaOperacaoPedidoPolicy;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use App\Services\Operacao\VendaOperacaoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticationAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('permissoes:criar');
    }

    public function test_panel_access_depends_on_administrative_approval_not_unused_email_verification_flow(): void
    {
        $approved = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => null,
        ]);
        $blocked = User::factory()->create([
            'email_approved' => false,
            'email_verified_at' => now(),
        ]);

        $panel = Filament::getPanel('painel');

        $this->assertTrue($approved->canAccessPanel($panel));
        $this->assertFalse($blocked->canAccessPanel($panel));

        $this->actingAs($blocked)
            ->get('/painel')
            ->assertForbidden();
    }

    public function test_default_roles_expose_an_explicit_and_least_privilege_manager_matrix(): void
    {
        $vendedor = Role::findByName(RolesEnum::Vendedor->value, 'web');
        $gestor = Role::findByName(RolesEnum::Gestor->value, 'web');
        $admin = Role::findByName(RolesEnum::Admin->value, 'web');

        $this->assertTrue($vendedor->hasPermissionTo(PermissoesEnum::ListarProdutosCRM->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::ListarInsumos->value));
        $this->assertFalse($vendedor->hasPermissionTo(PermissoesEnum::AprovarDesconto->value));

        $this->assertTrue($gestor->hasPermissionTo(PermissoesEnum::AprovarDesconto->value));
        $this->assertTrue($gestor->hasPermissionTo(PermissoesEnum::ListarDashboardBI->value));
        $this->assertFalse($gestor->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value));
        $this->assertFalse($gestor->hasPermissionTo(PermissoesEnum::EditarInsumos->value));
        $this->assertFalse($gestor->hasPermissionTo(PermissoesEnum::EditarUsuarios->value));
        $this->assertFalse($gestor->hasPermissionTo(PermissoesEnum::EditarRegrasTributarias->value));

        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::EditarProdutosCRM->value));
        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::EditarInsumos->value));
        $this->assertTrue($admin->hasPermissionTo(PermissoesEnum::EditarUsuarios->value));
    }

    public function test_seller_queries_and_backend_policies_only_allow_owned_crm_and_sales_records(): void
    {
        $sellerA = $this->userWithRole(RolesEnum::Vendedor);
        $sellerB = $this->userWithRole(RolesEnum::Vendedor);
        $manager = $this->userWithRole(RolesEnum::Gestor);
        $admin = $this->userWithRole(RolesEnum::Admin);

        $client = Cliente::query()->create(['razao_social' => 'Cliente de Escopo']);
        $stage = Etapa::query()->create([
            'nome' => 'Aberta',
            'slug' => 'aberta-auth',
            'ordem' => 1,
            'fechamento' => false,
        ]);

        $opportunityA = $this->createOpportunity($sellerA, $client, $stage, 'Oportunidade A');
        $opportunityB = $this->createOpportunity($sellerB, $client, $stage, 'Oportunidade B');

        $this->assertSame([$opportunityA->id], Oportunidade::query()->visiveisPara($sellerA)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$opportunityA->id, $opportunityB->id],
            Oportunidade::query()->visiveisPara($manager)->pluck('id')->all(),
        );
        $this->assertCount(2, Oportunidade::query()->visiveisPara($admin)->get());

        $opportunityPolicy = app(OportunidadePolicy::class);
        $this->assertTrue($opportunityPolicy->view($sellerA, $opportunityA));
        $this->assertFalse($opportunityPolicy->view($sellerA, $opportunityB));
        $this->assertTrue($opportunityPolicy->view($manager, $opportunityB));

        $saleA = $this->createSale($sellerA);
        $saleB = $this->createSale($sellerB);
        $legacyUnowned = $this->createSale(null);

        $this->assertSame(
            [$sellerA->id],
            app(VendaOperacaoService::class)->queryPorPerfil($sellerA)->pluck('user_id')->unique()->all(),
        );
        $this->assertCount(3, app(VendaOperacaoService::class)->queryPorPerfil($manager)->get());
        $this->assertCount(3, app(VendaOperacaoService::class)->queryPorPerfil($admin)->get());

        $salePolicy = app(VendaOperacaoPedidoPolicy::class);
        $this->assertTrue($salePolicy->view($sellerA, $saleA));
        $this->assertFalse($salePolicy->view($sellerA, $saleB));
        $this->assertFalse($salePolicy->view($sellerA, $legacyUnowned));
        $this->assertTrue($salePolicy->view($manager, $saleB));
        $this->assertTrue($salePolicy->view($admin, $legacyUnowned));
    }

    public function test_catalog_stock_and_user_administration_remain_permission_and_hierarchy_guarded(): void
    {
        $seller = $this->userWithRole(RolesEnum::Vendedor);
        $manager = $this->userWithRole(RolesEnum::Gestor);
        $admin = $this->userWithRole(RolesEnum::Admin);
        $superAdmin = $this->userWithRole(RolesEnum::SuperAdmin);

        $product = new Produto;
        $input = new Insumo;

        $this->assertTrue(app(ProdutoPolicy::class)->viewAny($seller));
        $this->assertFalse(app(ProdutoPolicy::class)->update($seller, $product));
        $this->assertFalse(app(ProdutoMovimentacaoPolicy::class)->create($seller));
        $this->assertFalse(app(InsumoPolicy::class)->viewAny($seller));
        $this->assertFalse(app(InsumoPolicy::class)->update($manager, $input));
        $this->assertTrue(app(ProdutoPolicy::class)->update($admin, $product));
        $this->assertTrue(app(InsumoPolicy::class)->update($admin, $input));

        $this->assertFalse(app(RoleService::class)->podeEscolherVendedor($seller));
        $this->assertTrue(app(RoleService::class)->podeEscolherVendedor($manager));

        $this->actingAs($admin);
        $userService = app(UserService::class);
        $this->assertTrue($userService->podeVerToggleAprovacaoEmail($admin, $seller, 'table'));
        $this->assertFalse($userService->podeVerToggleAprovacaoEmail($admin, $superAdmin, 'table'));
        $this->assertFalse($userService->podeVerToggleAprovacaoEmail($admin, $admin, 'table'));

        $userPolicy = app(UserPolicy::class);
        $this->assertTrue($userPolicy->update($admin, $seller));
        $this->assertFalse($userPolicy->update($admin, $superAdmin));
        $this->assertFalse($userPolicy->delete($admin, $admin));
        $this->assertFalse($userPolicy->delete($superAdmin, $superAdmin));
        $this->assertTrue($userPolicy->delete($superAdmin, $manager));
    }

    private function userWithRole(RolesEnum $role): User
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function createOpportunity(User $owner, Cliente $client, Etapa $stage, string $title): Oportunidade
    {
        return Oportunidade::query()->create([
            'titulo' => $title,
            'cliente_id' => $client->id,
            'etapa_id' => $stage->id,
            'user_id' => $owner->id,
            'temperatura' => 'warm',
        ]);
    }

    private function createSale(?User $owner): VendaOperacaoPedido
    {
        return VendaOperacaoPedido::query()->create([
            'user_id' => $owner?->id,
            'status' => VendaOperacaoPedido::STATUS_RASCUNHO,
            'data_venda' => now()->toDateString(),
            'vendedor_nome_snapshot' => $owner?->name,
        ]);
    }
}
