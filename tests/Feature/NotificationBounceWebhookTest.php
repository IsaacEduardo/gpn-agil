<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBounceWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mail_webhook.token', 'segredo-teste');
    }

    public function test_bounce_valido_desativa_email_e_regista(): void
    {
        $user = User::factory()->create(['email' => 'ricochete@example.com']);

        $this->withHeaders(['X-Webhook-Token' => 'segredo-teste'])
            ->postJson(route('webhooks.mail.bounce'), [
                'email' => 'ricochete@example.com',
                'type' => 'bounce',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'affected' => 1]);

        // O canal de e-mail fica desativado para o utilizador.
        $this->assertFalse(app(NotificationPreferenceService::class)->isEnabled($user, 'tarefas', 'mail'));

        // Fica registado na auditoria de entrega.
        $this->assertDatabaseHas('notification_deliveries', [
            'notifiable_id' => $user->id,
            'channel' => 'mail',
            'status' => 'bounced',
        ]);
    }

    public function test_token_invalido_e_rejeitado(): void
    {
        User::factory()->create(['email' => 'x@example.com']);

        $this->withHeaders(['X-Webhook-Token' => 'errado'])
            ->postJson(route('webhooks.mail.bounce'), ['email' => 'x@example.com'])
            ->assertStatus(401);
    }
}
