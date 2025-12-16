<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_mark_as_read(): void
    {
        config()->set('queue.default', 'sync');

        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new SimpleBroadcastNotification('N1', 'M1', url('/')));
        $user->notify(new SimpleBroadcastNotification('N2', 'M2', url('/')));

        $res = $this->getJson(route('notifications.index'));
        $res->assertOk();
        $json = $res->json();
        $this->assertEquals(2, $json['unread_count']);
        $this->assertIsArray($json['notifications']);
        $this->assertGreaterThanOrEqual(1, count($json['notifications']));

        $id = $json['notifications'][0]['id'];
        $res2 = $this->postJson(route('notifications.read', $id));
        $res2->assertOk();
        $this->assertTrue((bool) data_get($res2->json(), 'ok'));
        $this->assertEquals(1, data_get($res2->json(), 'unread_count'));

        $this->postJson(route('notifications.read', 'non-existent'))
            ->assertStatus(404);
    }

    public function test_mark_all_as_read(): void
    {
        config()->set('queue.default', 'sync');
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->notify(new SimpleBroadcastNotification('N1', 'M1', url('/')));
        $user->notify(new SimpleBroadcastNotification('N2', 'M2', url('/')));
        $this->postJson(route('notifications.read_all'))
            ->assertOk()
            ->assertJson(['ok' => true, 'unread_count' => 0]);
        $res = $this->getJson(route('notifications.index'));
        $res->assertOk();
        $json = $res->json();
        $this->assertEquals(0, $json['unread_count']);
        foreach ($json['notifications'] as $n) {
            $this->assertNotNull($n['read_at']);
        }
    }
}
