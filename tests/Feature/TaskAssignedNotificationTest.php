<?php

namespace Tests\Feature;

use App\Events\TaskAssigned;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\TarefaDelegadaNotification;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $actor;
    protected DocumentoEntrada $documento;
    protected Gabinete $gabinete;
    protected Departamento $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::create([
            'nome' => 'Gabinete Teste',
            'sigla' => 'GT',
        ]);

        $this->department = Departamento::create([
            'nome' => 'Departamento Teste',
            'sigla' => 'DT',
            'email' => 'teste-dep@example.com',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->actor = User::factory()->create([
            'departamento_id' => $this->department->id,
            'email' => 'actor@example.com',
        ]);

        $this->documento = DocumentoEntrada::create([
            'departamento_id' => $this->department->id,
            'user_id' => $this->actor->id,
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'assunto' => 'Documento de Teste para Tarefas',
            'status' => 'recebido',
            'data_entrada' => now(),
        ]);
    }

    public function test_creating_task_dispatches_task_assigned_event(): void
    {
        Event::fake([TaskAssigned::class]);

        $service = app(DocumentoEntradaService::class);
        $user = User::factory()->create(['email' => 'assigned@example.com']);

        $data = [
            'titulo' => 'Minha nova tarefa',
            'descricao' => 'Descrição detalhada da tarefa',
            'assigned_to_user_id' => $user->id,
            'prazo_at' => now()->addDays(5)->toDateTimeString(),
        ];

        $service->createTask($this->documento, $data, $this->actor);

        Event::assertDispatched(TaskAssigned::class, function ($event) use ($data) {
            return $event->tarefa->titulo === $data['titulo']
                && (int) $event->tarefa->documento_entrada_id === (int) $this->documento->id;
        });
    }

    public function test_individual_task_delegation_sends_email_notification(): void
    {
        Notification::fake();

        $service = app(DocumentoEntradaService::class);
        $user = User::factory()->create(['email' => 'assigned@example.com']);

        $data = [
            'titulo' => 'Tarefa Individual de Teste',
            'descricao' => 'Descrição individual',
            'assigned_to_user_id' => $user->id,
            'prazo_at' => now()->addDays(2)->toDateTimeString(),
        ];

        $tarefa = $service->createTask($this->documento, $data, $this->actor);

        Notification::assertSentTo(
            $user,
            TarefaDelegadaNotification::class,
            function ($notification, $channels) use ($tarefa) {
                $this->assertContains('mail', $channels);
                return (int) $notification->tarefa->id === (int) $tarefa->id;
            }
        );
    }

    public function test_department_task_delegation_sends_email_notification_to_all_members(): void
    {
        Notification::fake();

        // Configurar chefe do departamento
        $chefe = User::factory()->create(['email' => 'chefe@example.com']);
        $this->department->responsavel_id = $chefe->id;
        $this->department->save();

        $membro1 = User::factory()->create(['email' => 'membro1@example.com', 'departamento_id' => $this->department->id]);
        $membro2 = User::factory()->create(['email' => 'membro2@example.com', 'departamento_id' => $this->department->id]);

        // Membro sem e-mail (não deve ser notificado por e-mail)
        $membroSemEmail = User::factory()->create(['email' => '', 'departamento_id' => $this->department->id]);

        // Configurar responsável do gabinete
        $responsavelGab = User::factory()->create(['email' => 'resp-gab@example.com']);
        $this->gabinete->responsavel_id = $responsavelGab->id;
        $this->gabinete->save();

        $service = app(DocumentoEntradaService::class);

        $data = [
            'titulo' => 'Tarefa de Departamento de Teste',
            'descricao' => 'Descrição do departamento',
            'assigned_to_departamento_id' => $this->department->id,
            'prazo_at' => now()->addDays(3)->toDateTimeString(),
        ];

        $tarefa = $service->createTask($this->documento, $data, $this->actor);

        // Chefes e membros com e-mail devem receber
        Notification::assertSentTo($chefe, TarefaDelegadaNotification::class);
        Notification::assertSentTo($membro1, TarefaDelegadaNotification::class);
        Notification::assertSentTo($membro2, TarefaDelegadaNotification::class);
        Notification::assertSentTo($responsavelGab, TarefaDelegadaNotification::class);

        // Membro sem e-mail não deve receber
        Notification::assertNotSentTo($membroSemEmail, TarefaDelegadaNotification::class);
    }

    public function test_email_notification_contains_correct_markdown_details(): void
    {
        $user = User::factory()->create(['email' => 'recipient@example.com']);
        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $this->documento->id,
            'titulo' => 'Tarefa de Detalhes',
            'descricao' => 'Esta é a descrição detalhada.',
            'assigned_by_id' => $this->actor->id,
            'assigned_to_user_id' => $user->id,
            'prazo_at' => now()->addDays(1),
            'status' => 'pendente',
        ]);

        $notification = new TarefaDelegadaNotification($tarefa);
        $mailMessage = $notification->toMail($user);

        $this->assertEquals("Nova Tarefa Designada: Tarefa de Detalhes", $mailMessage->subject);
        $this->assertEquals("emails.tarefas.delegada", $mailMessage->markdown);
        
        $viewData = $mailMessage->viewData;
        $this->assertEquals($tarefa->id, $viewData['tarefa']->id);
        $this->assertEquals($this->documento->id, $viewData['documento']->id);
        $this->assertStringContainsString('001/2026', $viewData['numero']);
    }
}
