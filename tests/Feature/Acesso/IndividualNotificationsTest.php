<?php

namespace Tests\Feature\Acesso;

use App\Models\Acesso\User;
use App\Services\Acesso\UserNotificationService;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_persisted_notifications_and_actions_are_scoped_to_the_authenticated_user(): void
    {
        $userA = User::factory()->create(['email_approved' => true]);
        $userB = User::factory()->create(['email_approved' => true]);
        $notifications = app(UserNotificationService::class);

        $notifications->send($userA, Notification::make()->title('Somente A'));
        $notifications->send($userB, Notification::make()->title('Somente B'));

        $notificationA = $userA->notifications()->sole();
        $notificationB = $userB->notifications()->sole();

        $this->assertSame('Somente A', $notificationA->data['title']);
        $this->assertSame('Somente B', $notificationB->data['title']);
        $this->assertSame($userA->id, $notificationA->notifiable_id);
        $this->assertSame($userB->id, $notificationB->notifiable_id);

        $this->actingAs($userB);
        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $component = new DatabaseNotifications;

        $this->assertSame([$notificationB->id], $component->getNotificationsQuery()->pluck('id')->all());

        // Even with another user's UUID, the component updates through the
        // authenticated user's relationship and cannot touch that record.
        $component->markNotificationAsRead($notificationA->id);
        $this->assertNull($notificationA->fresh()->read_at);

        $component->markNotificationAsRead($notificationB->id);
        $this->assertNotNull($notificationB->fresh()->read_at);
    }
}
