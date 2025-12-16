<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_persistence_and_broadcast_event(): void
    {
        // Executar canais em modo síncrono para ShouldQueue
        config()->set('queue.default', 'sync');

        $user = User::factory()->create();

        Event::fake([BroadcastNotificationCreated::class]);

        $user->notify(new SimpleBroadcastNotification('Teste', 'Mensagem de teste', url('/')));

        $this->assertEquals(1, $user->notifications()->count());

        Event::assertDispatched(BroadcastNotificationCreated::class, function ($event) use ($user) {
            return (int) $event->notifiable->id === (int) $user->id;
        });

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('Teste', data_get($notification->data, 'title'));
    }
}
