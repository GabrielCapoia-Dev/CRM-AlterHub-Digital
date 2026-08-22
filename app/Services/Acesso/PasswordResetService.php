<?php

namespace App\Services\Acesso;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class PasswordResetService
{
    public const DEFAULT_TEMPORARY_PASSWORD = 'Mudar@123';

    public const MIN_PASSWORD_LENGTH = 8;

    public const MAX_PASSWORD_LENGTH = 30;

    public function __construct(
        private readonly UserNotificationService $notifications,
    ) {}

    public function canReset(User $actor, User $target): bool
    {
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            return false;
        }

        if (! $actor->hasAnyRole([
            RolesEnum::Admin->value,
            RolesEnum::SuperAdmin->value,
        ])) {
            return false;
        }

        if ($target->hasRole(RolesEnum::SuperAdmin->value)) {
            return false;
        }

        return ! $target->hasRole(RolesEnum::Admin->value)
            || $actor->hasRole(RolesEnum::SuperAdmin->value);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function resetWithTemporaryPassword(
        User $actor,
        User $target,
        string $temporaryPassword,
    ): void {
        if (! $this->canReset($actor, $target)) {
            throw new AuthorizationException('Você não pode redefinir a senha deste usuário.');
        }

        $this->validateTemporaryPassword($temporaryPassword);

        DB::transaction(function () use ($actor, $target, $temporaryPassword): void {
            $target->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'password_reset_at' => now(),
                'password_reset_by' => $actor->getKey(),
                'password_changed_at' => null,
                'remember_token' => Str::random(60),
            ])->save();
        });

        $this->notifications->send(
            $target,
            Notification::make()
                ->warning()
                ->title('Sua senha foi redefinida')
                ->body('Entre com a senha temporária informada pelo administrador e cadastre uma nova senha para continuar.'),
        );
    }

    /**
     * @throws ValidationException
     */
    public function completeRequiredChange(
        User $user,
        string $temporaryPassword,
        string $newPassword,
    ): void {
        if (! $user->must_change_password) {
            return;
        }

        if (! Hash::check($temporaryPassword, $user->password)) {
            throw ValidationException::withMessages([
                'temporary_password' => 'A senha temporária informada está incorreta.',
            ]);
        }

        $this->validateUserPassword($newPassword);

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ser diferente da senha temporária.',
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateTemporaryPassword(string $password): void
    {
        Validator::make(
            ['temporary_password' => $password],
            [
                'temporary_password' => [
                    'required',
                    'string',
                    'max:'.self::MAX_PASSWORD_LENGTH,
                    Password::min(self::MIN_PASSWORD_LENGTH)->mixedCase()->numbers()->symbols(),
                ],
            ],
            [
                'temporary_password.required' => 'Informe a senha temporária.',
                'temporary_password.max' => 'A senha temporária deve ter no máximo 30 caracteres.',
            ],
        )->validate();
    }

    /**
     * @throws ValidationException
     */
    private function validateUserPassword(string $password): void
    {
        Validator::make(
            ['password' => $password],
            [
                'password' => [
                    'required',
                    'string',
                    'max:'.self::MAX_PASSWORD_LENGTH,
                    Password::min(max(8, (int) config('crm.access.user_password_min_length', 8)))
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
            ],
            [
                'password.max' => 'A senha deve ter no máximo '.self::MAX_PASSWORD_LENGTH.' caracteres.',
            ],
        )->validate();
    }
}
