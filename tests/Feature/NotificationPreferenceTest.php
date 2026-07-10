<?php

namespace Tests\Feature;

use App\Listeners\ApplyNotificationPreferences;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function listener(): ApplyNotificationPreferences
    {
        return new ApplyNotificationPreferences(app(NotificationPreferenceService::class));
    }

    private function sending(User $user, string $channel): NotificationSending
    {
        // type 'sla_critical' -> categoria 'sla'
        $notification = new SimpleBroadcastNotification('Alerta', 'Corpo', url('/'), 'urgent', 'sla_critical');

        return new NotificationSending($user, $notification, $channel);
    }

    public function test_por_omissao_todos_os_canais_estao_ativos(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->listener()->handle($this->sending($user, 'mail')));
        $this->assertTrue($this->listener()->handle($this->sending($user, 'broadcast')));
    }

    public function test_desativar_canal_suprime_esse_canal(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id, 'category' => 'sla', 'channel' => 'mail', 'enabled' => false,
        ]);

        // mail suprimido; broadcast intacto
        $this->assertFalse($this->listener()->handle($this->sending($user, 'mail')));
        $this->assertTrue($this->listener()->handle($this->sending($user, 'broadcast')));
    }

    public function test_canal_database_nunca_e_suprimido(): void
    {
        $user = User::factory()->create();
        // Mesmo com uma linha a desativar, o canal in-app mantém-se.
        NotificationPreference::create([
            'user_id' => $user->id, 'category' => 'sla', 'channel' => 'database', 'enabled' => false,
        ]);

        $this->assertTrue($this->listener()->handle($this->sending($user, 'database')));
    }

    public function test_sync_e_matrix_refletem_as_escolhas(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationPreferenceService::class);

        // Desativa tarefas/mail; mantém o resto.
        $service->sync($user, ['tarefas' => ['broadcast' => '1'], 'sla' => ['mail' => '1', 'broadcast' => '1']]);

        $matrix = $service->matrixFor($user);
        $this->assertFalse($matrix['tarefas']['mail']);
        $this->assertTrue($matrix['tarefas']['broadcast']);
        $this->assertTrue($matrix['sla']['mail']);
        $this->assertFalse($service->isEnabled($user, 'tarefas', 'mail'));
        $this->assertTrue($service->isEnabled($user, 'sla', 'mail'));
    }

    public function test_pagina_de_preferencias_abre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.preferences.edit'))
            ->assertOk()
            ->assertSee('Preferências de notificação');
    }

    public function test_submissao_persiste_o_opt_out(): void
    {
        $user = User::factory()->create();

        // Envia apenas tarefas/broadcast marcado -> tarefas/mail fica desativado.
        $this->actingAs($user)
            ->put(route('notifications.preferences.update'), [
                'prefs' => ['tarefas' => ['broadcast' => '1']],
            ])
            ->assertRedirect(route('notifications.preferences.edit'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id, 'category' => 'tarefas', 'channel' => 'mail', 'enabled' => false,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id, 'category' => 'tarefas', 'channel' => 'broadcast', 'enabled' => true,
        ]);
    }
}
