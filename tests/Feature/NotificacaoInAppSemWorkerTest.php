<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O aviso in-app tem de chegar mesmo sem worker de filas a correr.
 *
 * Em teste, QUEUE_CONNECTION é 'sync' (ver phpunit.xml), pelo que tudo o que é
 * enfileirado corre na hora e nenhum teste conseguia apanhar este defeito. Em
 * desenvolvimento e em produção a ligação é 'database': sem worker, as
 * notificações de registo, de despacho e de tarefa delegada ficavam paradas na
 * tabela `jobs` e o ecrã de notificações do destinatário mostrava-se vazio, sem
 * erro visível. As únicas que chegavam eram as não enfileiradas.
 *
 * Estes testes forçam a ligação de filas a 'database' — o cenário real — e
 * exigem que a linha in-app exista imediatamente, sem ninguém processar a fila.
 *
 * @see \App\Notifications\Concerns\QueuedRetryPolicy::viaConnections()
 */
class NotificacaoInAppSemWorkerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Põe a aplicação no cenário real: há fila persistente e não há worker.
     */
    private function semWorkerAProcessarAFila(): void
    {
        config(['queue.default' => 'database']);
    }

    private function cenario(): array
    {
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);

        $gab = Gabinete::create(['nome' => 'Gabinete Fluxo', 'sigla' => 'GFLX']);
        $dep = Departamento::create(['nome' => 'Departamento Alfa', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $dep->id]);
        $tecnico = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);
        $balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);

        return compact('gab', 'dep', 'chefe', 'tecnico', 'balcao');
    }

    private function contarNotificacoes(User $user): int
    {
        return $user->notifications()->count();
    }

    public function test_registo_entrega_o_aviso_in_app_sem_worker(): void
    {
        Storage::fake('public');
        $this->semWorkerAProcessarAFila();

        ['dep' => $dep, 'chefe' => $chefe, 'balcao' => $balcao] = $this->cenario();

        $this->actingAs($balcao);

        app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Pedido de parecer urgente',
            'departamento_id' => $dep->id,
            'procedencia' => 'Ministério das Finanças',
        ]);

        $this->assertSame(
            1,
            $this->contarNotificacoes($chefe),
            'A chefia do departamento de destino tem de ser avisada do registo sem depender de um worker.'
        );
    }

    public function test_tarefa_delegada_entrega_o_aviso_in_app_sem_worker(): void
    {
        Storage::fake('public');
        $this->semWorkerAProcessarAFila();

        ['dep' => $dep, 'chefe' => $chefe, 'tecnico' => $tecnico, 'balcao' => $balcao] = $this->cenario();

        $this->actingAs($balcao);
        $service = app(DocumentoEntradaService::class);

        $doc = $service->createDocument([
            'assunto' => 'Documento a delegar',
            'departamento_id' => $dep->id,
        ]);

        $antes = $this->contarNotificacoes($tecnico);

        $service->createTask($doc, [
            'titulo' => 'Elaborar parecer técnico',
            'assigned_to_user_id' => $tecnico->id,
        ], $chefe);

        $this->assertSame(
            $antes + 1,
            $this->contarNotificacoes($tecnico),
            'O executante tem de ser avisado da tarefa que lhe foi atribuída sem depender de um worker.'
        );
    }

    /**
     * O e-mail e o broadcast falam com serviços externos (SMTP, Reverb) e não
     * podem atrasar nem fazer falhar o pedido: esses continuam enfileirados.
     */
    public function test_canais_externos_continuam_enfileirados(): void
    {
        Storage::fake('public');
        $this->semWorkerAProcessarAFila();
        Queue::fake();

        ['dep' => $dep, 'chefe' => $chefe, 'tecnico' => $tecnico, 'balcao' => $balcao] = $this->cenario();

        $this->actingAs($balcao);
        $service = app(DocumentoEntradaService::class);

        $doc = $service->createDocument([
            'assunto' => 'Documento a delegar',
            'departamento_id' => $dep->id,
        ]);

        $service->createTask($doc, [
            'titulo' => 'Elaborar parecer técnico',
            'assigned_to_user_id' => $tecnico->id,
        ], $chefe);

        // O técnico da factory tem e-mail, logo o canal 'mail' entra no via().
        Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class);
    }

    /**
     * Guarda-costas do próprio mecanismo: se alguém remover viaConnections(),
     * o canal in-app volta a depender do worker e os testes acima só passariam
     * por a suite correr em 'sync'.
     */
    public function test_canal_in_app_esta_declarado_como_sincrono(): void
    {
        $notificacao = new \App\Notifications\TarefaDelegadaNotification(new DocumentoTarefa);

        $this->assertSame('sync', $notificacao->viaConnections()['database'] ?? null);
    }
}
