<?php

namespace Tests\Feature;

use App\Listeners\ApplyNotificationPreferences;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationQuietHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userComQuietHours(): User
    {
        $user = User::factory()->create();
        app(NotificationPreferenceService::class)->saveSettings($user, [
            'quiet_hours_enabled' => true,
            'quiet_start' => '22:00',
            'quiet_end' => '07:00',
            'timezone' => 'UTC',
            'digest_enabled' => false,
        ]);

        return $user;
    }

    private function listener(): ApplyNotificationPreferences
    {
        return new ApplyNotificationPreferences(app(NotificationPreferenceService::class));
    }

    private function sending(User $user, string $channel, string $priority, string $type): NotificationSending
    {
        return new NotificationSending($user, new SimpleBroadcastNotification('t', 'b', url('/'), $priority, $type), $channel);
    }

    public function test_dentro_do_periodo_suprime_nao_urgente_mas_deixa_urgente(): void
    {
        $user = $this->userComQuietHours();
        Carbon::setTestNow(Carbon::parse('2026-07-10 23:30:00', 'UTC'));

        // Não-urgente por e-mail: suprimido durante o silêncio.
        $this->assertFalse($this->listener()->handle($this->sending($user, 'mail', 'normal', 'documento_recebido')));
        // Urgente: passa sempre.
        $this->assertTrue($this->listener()->handle($this->sending($user, 'mail', 'urgent', 'sla_critical')));
    }

    public function test_fora_do_periodo_permite_tudo(): void
    {
        $user = $this->userComQuietHours();
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00', 'UTC'));

        $this->assertTrue($this->listener()->handle($this->sending($user, 'mail', 'normal', 'documento_recebido')));
        $this->assertTrue($this->listener()->handle($this->sending($user, 'broadcast', 'normal', 'documento_recebido')));
    }
}
