<?php

namespace App\Services\Acesso;

use App\Models\Acesso\User;
use Filament\Notifications\Notification;

final class UserNotificationService
{
    /**
     * Persist a notification for exactly one explicitly selected recipient.
     *
     * Keeping the recipient singular prevents accidental system-wide sends and
     * relies on Laravel's notifiable type/id columns for tenant-like isolation.
     */
    public function send(User $recipient, Notification $notification): void
    {
        $notification->sendToDatabase($recipient, isEventDispatched: true);
    }
}
