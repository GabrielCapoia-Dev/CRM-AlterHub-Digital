<?php

namespace App\Listeners;

use App\Models\Acesso\User;
use Illuminate\Auth\Events\PasswordReset;

class RecordSelfServicePasswordReset
{
    public function handle(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
    }
}
