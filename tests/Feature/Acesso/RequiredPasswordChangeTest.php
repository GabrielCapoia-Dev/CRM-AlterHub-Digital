<?php

namespace Tests\Feature\Acesso;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Acesso\User;
use App\Services\Acesso\PasswordResetService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequiredPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPORARY_PASSWORD = 'Temporaria#2026Segura';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crm.access.user_password_min_length', 8);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_reset_is_audited_forces_change_and_notifies_only_the_target(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $target = User::factory()->create(['password' => 'Antiga#2026']);
        $other = User::factory()->create();

        app(PasswordResetService::class)->resetWithTemporaryPassword(
            $admin,
            $target,
            self::TEMPORARY_PASSWORD,
        );

        $target->refresh();

        $this->assertTrue(Hash::check(self::TEMPORARY_PASSWORD, $target->password));
        $this->assertFalse(Hash::check('Antiga#2026', $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertSame($admin->id, $target->password_reset_by);
        $this->assertNotNull($target->password_reset_at);
        $this->assertNull($target->password_changed_at);
        $this->assertCount(1, $target->notifications);
        $this->assertSame('Sua senha foi redefinida', $target->notifications->first()->data['title']);
        $this->assertCount(0, $admin->notifications);
        $this->assertCount(0, $other->notifications);
    }

    public function test_reset_respects_administrative_hierarchy_and_never_allows_self_reset(): void
    {
        $seller = $this->userWithRole(RolesEnum::Vendedor);
        $adminA = $this->userWithRole(RolesEnum::Admin);
        $adminB = $this->userWithRole(RolesEnum::Admin);
        $superAdmin = $this->userWithRole(RolesEnum::SuperAdmin);
        $target = User::factory()->create();
        $service = app(PasswordResetService::class);

        $this->assertFalse($service->canReset($seller, $target));
        $this->assertFalse($service->canReset($adminA, $adminA));
        $this->assertFalse($service->canReset($adminA, $adminB));
        $this->assertTrue($service->canReset($adminA, $target));
        $this->assertTrue($service->canReset($superAdmin, $adminB));
        $this->assertFalse($service->canReset($superAdmin, $superAdmin));

        $this->expectException(AuthorizationException::class);
        $service->resetWithTemporaryPassword($seller, $target, self::TEMPORARY_PASSWORD);
    }

    public function test_reset_rejects_a_weak_temporary_password_without_changing_the_user(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $target = User::factory()->create(['password' => 'Antiga#2026']);

        try {
            app(PasswordResetService::class)->resetWithTemporaryPassword(
                $admin,
                $target,
                'senha-fraca',
            );
            $this->fail('Uma senha temporária fraca deveria impedir a redefinição.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('temporary_password', $exception->errors());
        }

        $target->refresh();
        $this->assertTrue(Hash::check('Antiga#2026', $target->password));
        $this->assertFalse($target->must_change_password);
        $this->assertCount(0, $target->notifications);
    }

    public function test_reset_modal_prefills_default_and_accepts_a_custom_temporary_password(): void
    {
        foreach (PermissoesEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $admin = $this->userWithRole(RolesEnum::SuperAdmin);
        $admin->givePermissionTo(PermissoesEnum::ListarUsuarios->value);
        $target = User::factory()->create(['password' => 'Antiga#2026']);

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $this->actingAs($admin);

        $component = Livewire::test(ListUsers::class)
            ->mountTableAction('resetar_senha', $target)
            ->assertTableActionDataSet([
                'temporary_password' => PasswordResetService::DEFAULT_TEMPORARY_PASSWORD,
            ]);

        $this->assertSame('Mudar@123', PasswordResetService::DEFAULT_TEMPORARY_PASSWORD);

        $component
            ->setTableActionData(['temporary_password' => self::TEMPORARY_PASSWORD])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $target->refresh();
        $this->assertTrue(Hash::check(self::TEMPORARY_PASSWORD, $target->password));
        $this->assertTrue($target->must_change_password);
    }

    public function test_login_with_temporary_password_is_redirected_until_a_new_password_is_saved(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $target = User::factory()->create([
            'email_approved' => true,
            'password' => 'Antiga#2026',
        ]);
        app(PasswordResetService::class)->resetWithTemporaryPassword(
            $admin,
            $target,
            self::TEMPORARY_PASSWORD,
        );

        Auth::logout();
        $this->assertFalse(Auth::attempt([
            'email' => $target->email,
            'password' => 'Antiga#2026',
        ]));
        $this->assertTrue(Auth::attempt([
            'email' => $target->email,
            'password' => self::TEMPORARY_PASSWORD,
        ]));

        $this->get(route('filament.painel.auth.profile'))
            ->assertRedirect(route('password.change-required.edit'));
        $this->get(route('password.change-required.edit'))
            ->assertOk()
            ->assertSee('Crie uma nova senha')
            ->assertSee($target->name);

        $this->post(route('default-livewire.update'))
            ->assertRedirect(route('password.change-required.edit'));

        $this->post(route('password.change-required.update'), [
            'temporary_password' => self::TEMPORARY_PASSWORD,
            'password' => self::TEMPORARY_PASSWORD,
            'password_confirmation' => self::TEMPORARY_PASSWORD,
        ])->assertSessionHasErrors('password');

        $this->assertTrue($target->fresh()->must_change_password);

        $newPassword = 'MinhaNova#2026Senha';
        $this->post(route('password.change-required.update'), [
            'temporary_password' => self::TEMPORARY_PASSWORD,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('filament.painel.home'));

        $target->refresh();
        $this->assertFalse($target->must_change_password);
        $this->assertNotNull($target->password_changed_at);
        $this->assertTrue(Hash::check($newPassword, $target->password));

        $this->get(route('filament.painel.auth.profile'))->assertOk();

        $this->post(route('filament.painel.auth.logout'));
        $this->assertGuest();
    }

    public function test_session_opened_before_reset_cannot_change_password_without_the_temporary_password(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $target = User::factory()->create([
            'email_approved' => true,
            'password' => 'Antiga#2026',
        ]);
        $newPassword = 'MinhaNova#2026Senha';

        $this->actingAs($target);
        app(PasswordResetService::class)->resetWithTemporaryPassword(
            $admin,
            $target,
            self::TEMPORARY_PASSWORD,
        );

        $this->post(route('password.change-required.update'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasErrors('temporary_password');

        $target->refresh();
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check(self::TEMPORARY_PASSWORD, $target->password));

        $this->post(route('password.change-required.update'), [
            'temporary_password' => 'Antiga#2026',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasErrors('temporary_password');

        $this->post(route('password.change-required.update'), [
            'temporary_password' => self::TEMPORARY_PASSWORD,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('filament.painel.home'));

        $target->refresh();
        $this->assertFalse($target->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $target->password));
    }

    public function test_panel_session_opened_before_reset_is_revoked_before_the_password_change_screen(): void
    {
        $admin = $this->userWithRole(RolesEnum::Admin);
        $target = User::factory()->create([
            'email_approved' => true,
            'password' => 'Antiga#2026',
        ]);
        $oldPasswordHash = $target->getAuthPassword();

        $this->actingAs($target)
            ->withSession(['password_hash_web' => $oldPasswordHash]);

        app(PasswordResetService::class)->resetWithTemporaryPassword(
            $admin,
            $target,
            self::TEMPORARY_PASSWORD,
        );

        $this->get(route('password.change-required.edit'))
            ->assertRedirect(route('filament.painel.auth.login'));
        $this->assertGuest();
    }

    private function userWithRole(RolesEnum $role): User
    {
        $user = User::factory()->create(['email_approved' => true]);
        Role::findOrCreate($role->value, 'web');
        $user->assignRole($role->value);

        return $user;
    }
}
