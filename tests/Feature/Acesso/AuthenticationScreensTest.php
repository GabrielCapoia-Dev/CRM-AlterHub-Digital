<?php

namespace Tests\Feature\Acesso;

use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Auth\Pages\ResetPassword;
use App\Models\Acesso\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        config()->set('queue.default', 'sync');

        foreach ([
            RequestPasswordReset::class.'|request',
            ResetPassword::class.'|resetPassword',
        ] as $rateLimitedAction) {
            RateLimiter::clear('livewire-rate-limiter:'.sha1($rateLimitedAction.'|127.0.0.1'));
        }
    }

    public function test_root_redirects_to_the_panel_login(): void
    {
        $this->get('/')
            ->assertRedirect('/painel/login');
    }

    public function test_login_and_password_recovery_screens_use_the_new_flow(): void
    {
        $requestUrl = route('filament.painel.auth.password-reset.request');

        $this->get(route('filament.painel.auth.login'))
            ->assertOk()
            ->assertSee('Bem-vindo à Unibiotech')
            ->assertSee('Acessar o portal')
            ->assertSee($requestUrl, escape: false)
            ->assertSee('unibiotech-logo.svg')
            ->assertSee('leite-fermentado-2.jpg');

        $this->get($requestUrl)
            ->assertOk()
            ->assertSee('Recupere seu acesso ao portal')
            ->assertSee('Enviar link de redefinição');
    }

    public function test_approved_user_receives_a_branded_reset_link(): void
    {
        NotificationFacade::fake();

        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertNotified('Confira seu e-mail');

        NotificationFacade::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => str_contains(
                (string) $notification->url,
                '/painel/recuperar-senha/redefinir'
            )
        );
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_unknown_email_gets_the_same_generic_feedback_without_a_token(): void
    {
        NotificationFacade::fake();

        $email = 'nao-existe@example.invalid';

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $email])
            ->call('request')
            ->assertNotified('Confira seu e-mail');

        NotificationFacade::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
    }

    public function test_unapproved_user_does_not_receive_a_reset_token(): void
    {
        NotificationFacade::fake();

        $user = User::factory()->create([
            'email_approved' => false,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertNotified('Confira seu e-mail');

        NotificationFacade::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_valid_reset_token_changes_password_and_completes_a_pending_forced_change(): void
    {
        $admin = User::factory()->create();
        $resetAt = now()->subMinute();
        $rememberToken = Str::random(60);
        $user = User::factory()->create([
            'email_approved' => true,
            'password' => 'SenhaAntiga#2026',
            'must_change_password' => true,
            'password_reset_at' => $resetAt,
            'password_reset_by' => $admin->id,
            'remember_token' => $rememberToken,
        ]);
        $token = Password::broker()->createToken($user);
        $newPassword = 'NovaSenha#2026Forte';

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => $newPassword,
                'passwordConfirmation' => $newPassword,
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.painel.auth.login'));

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertSame($admin->id, $user->password_reset_by);
        $this->assertSame(
            $resetAt->format('Y-m-d H:i:s'),
            $user->password_reset_at?->format('Y-m-d H:i:s'),
        );
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_rejects_a_weak_password(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'password' => 'SenhaAntiga#2026',
        ]);
        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, [
            'email' => $user->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'senha-fraca',
                'passwordConfirmation' => 'senha-fraca',
            ])
            ->call('resetPassword')
            ->assertHasFormErrors(['password']);

        $this->assertTrue(Hash::check('SenhaAntiga#2026', $user->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }
}
