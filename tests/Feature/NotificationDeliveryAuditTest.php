<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeliveryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_regista_entrega_no_canal_database(): void
    {
        config()->set('queue.default', 'sync');

        $user = User::factory()->create();

        $user->notify(new SimpleBroadcastNotification('Alerta', 'Corpo', url('/'), 'urgent', 'sla_critical'));

        $this->assertDatabaseHas('notification_deliveries', [
            'notifiable_id' => $user->id,
            'notification_type' => SimpleBroadcastNotification::class,
            'event_type' => 'sla_critical',
            'channel' => 'database',
            'status' => 'sent',
        ]);
    }
}
