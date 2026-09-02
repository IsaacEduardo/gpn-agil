<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Support\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    public function test_user_can_fetch_notifications_and_unread_count()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        // Send a notification
        $user->notify(new SimpleBroadcastNotification(
            'Novo Documento Recebido',
            'O documento #123 foi recebido no departamento.',
            '/documentos-entradas/123',
            'documento_recebido'
        ));

        $response = $this->actingAs($user)->getJson('/api/notificacoes');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'unread_count',
            'filter',
            'notifications' => [
                '*' => [
                    'id',
                    'title',
                    'body',
                    'url',
                    'category',
                    'icon',
                    'is_read',
                ],
            ],
        ]);

        $this->assertSame(1, $response->json('unread_count'));
        $this->assertSame('Novo Documento Recebido', $response->json('notifications.0.title'));
        $this->assertSame('workflow', $response->json('notifications.0.category'));
    }

    public function test_user_can_filter_notifications_by_category()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        // 1 workflow notification
        $user->notify(new SimpleBroadcastNotification(
            'Tarefa Atribuída',
            'Você recebeu uma nova tarefa.',
            '/tarefas/1',
            'tarefa_delegada'
        ));

        // 1 system notification
        $user->notify(new SimpleBroadcastNotification(
            'Alerta de Armazenamento',
            'O disco está com 85% de uso.',
            '/admin/system',
            'system_alert'
        ));

        // Filter by workflow
        $responseWorkflow = $this->actingAs($user)->getJson('/api/notificacoes?filter=workflow');
        $responseWorkflow->assertStatus(200);
        $this->assertCount(1, $responseWorkflow->json('notifications'));
        $this->assertSame('Tarefa Atribuída', $responseWorkflow->json('notifications.0.title'));

        // Filter by system
        $responseSystem = $this->actingAs($user)->getJson('/api/notificacoes?filter=system');
        $responseSystem->assertStatus(200);
        $this->assertCount(1, $responseSystem->json('notifications'));
        $this->assertSame('Alerta de Armazenamento', $responseSystem->json('notifications.0.title'));
    }

    public function test_user_can_mark_single_notification_as_read()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        $user->notify(new SimpleBroadcastNotification('Alerta 1', 'Mensagem 1'));
        $notification = $user->notifications()->first();

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($user)->postJson("/api/notificacoes/{$notification->id}/ler");

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'unread_count' => 0]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        $user->notify(new SimpleBroadcastNotification('Alerta 1', 'Mensagem 1'));
        $user->notify(new SimpleBroadcastNotification('Alerta 2', 'Mensagem 2'));

        $this->assertSame(2, $user->unreadNotifications()->count());

        $response = $this->actingAs($user)->postJson('/api/notificacoes/ler-todas');

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'unread_count' => 0]);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_other_user_notification_as_read()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $userA = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);
        $userB = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        $userA->notify(new SimpleBroadcastNotification('Alerta A', 'Mensagem A'));
        $notifA = $userA->notifications()->first();

        $response = $this->actingAs($userB)->postJson("/api/notificacoes/{$notifA->id}/ler");

        $response->assertStatus(404);
        $this->assertNull($notifA->fresh()->read_at);
    }

    public function test_notification_presenter_formats_contextual_icons_and_categories()
    {
        $payload1 = [
            'titulo' => 'Nova Tarefa Delegada',
            'mensagem' => 'Elaborar parecer técnico',
            'action_url' => '/tarefas/10',
            'tipo' => 'tarefa_delegada',
        ];

        $res1 = NotificationPresenter::present($payload1);
        $this->assertSame('Nova Tarefa Delegada', $res1['title']);
        $this->assertSame('workflow', $res1['category']);
        $this->assertSame('fas fa-tasks', $res1['icon']);

        $payload2 = [
            'title' => 'Extração OCR Finalizada',
            'body' => 'Texto do documento extraído',
            'type' => 'ocr_completed',
        ];

        $res2 = NotificationPresenter::present($payload2);
        $this->assertSame('system', $res2['category']);
        $this->assertSame('fas fa-eye', $res2['icon']);
    }

    public function test_browser_request_to_notifications_url_renders_html_page()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gab = Gabinete::create(['nome' => 'Gabinete Teste']);
        $dep = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $dep->id]);

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertStatus(200);
        $response->assertSee('Minhas Notificações');
    }
}
