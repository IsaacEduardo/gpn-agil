<?php

namespace Tests\Unit\Notifications;

use App\Notifications\NotificationCategory;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushPreferencesTest extends TestCase
{
    use RefreshDatabase;
    public function test_canal_push_esta_registado_como_gerivel(): void
    {
        $this->assertTrue(NotificationCategory::isManageableChannel('push'));
        $this->assertArrayHasKey('push', NotificationCategory::MANAGEABLE_CHANNELS);
    }

    public function test_matriz_de_preferencias_inclui_canal_push(): void
    {
        $user = \App\Models\User::factory()->make(['id' => 999]);
        $service = new NotificationPreferenceService();

        $matrix = $service->matrixFor($user);

        $this->assertArrayHasKey('documentos', $matrix);
        $this->assertArrayHasKey('push', $matrix['documentos']);
        $this->assertTrue($matrix['documentos']['push']); // Por omissão ativo (opt-out model)
    }
}
