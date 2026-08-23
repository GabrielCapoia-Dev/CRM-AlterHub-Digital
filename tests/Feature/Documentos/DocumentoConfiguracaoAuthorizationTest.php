<?php

namespace Tests\Feature\Documentos;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Filament\Resources\DocumentoConfiguracoes\DocumentoConfiguracaoResource;
use App\Models\Acesso\User;
use App\Models\DocumentoConfiguracao;
use App\Policies\DocumentoConfiguracaoPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentoConfiguracaoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('permissoes:criar');
        Filament::setCurrentPanel(Filament::getPanel('painel'));
    }

    public function test_only_super_admin_can_view_create_or_update_document_settings(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $admin->givePermissionTo([
            PermissoesEnum::AcessarConfiguracoesDocumentos->value,
            PermissoesEnum::EditarConfiguracoesDocumentos->value,
        ]);
        $superAdmin = $this->userWithRole(RolesEnum::SuperAdmin);
        $policy = app(DocumentoConfiguracaoPolicy::class);

        $this->assertFalse($policy->viewAny($admin));
        $this->assertFalse($policy->create($admin));
        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->create($superAdmin));

        $settings = DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
        ]);

        $this->assertFalse($policy->view($admin, $settings));
        $this->assertFalse($policy->update($admin, $settings));
        $this->assertTrue($policy->view($superAdmin, $settings));
        $this->assertTrue($policy->update($superAdmin, $settings));
        $this->assertFalse($policy->create($superAdmin));
        $this->assertFalse($policy->delete($superAdmin, $settings));
    }

    public function test_settings_shortcut_and_direct_route_are_blocked_for_regular_admin(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $superAdmin = $this->userWithRole(RolesEnum::SuperAdmin);
        $url = DocumentoConfiguracaoResource::getUrl('index');

        $this->actingAs($admin);
        $this->assertFalse(DocumentoConfiguracaoResource::canAccess());
        $this->get($url)->assertForbidden();

        $this->actingAs($superAdmin);
        $this->assertTrue(DocumentoConfiguracaoResource::canAccess());
        $this->get($url)->assertOk();
    }

    private function userWithRole(RolesEnum $role): User
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'must_change_password' => false,
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
