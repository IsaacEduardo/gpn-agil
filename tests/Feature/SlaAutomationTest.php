<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    public function test_sla_automation_sends_notification_correctly()
    {
        Notification::fake();

        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'sigla' => 'DEPA', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create([
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
        ]);

        $owner = User::factory()->create([
            'role_id' => $roles['user']->id,
            'departamento_id' => $dep->id,
        ]);

        // 1. Doc novo (SLA normal)
        $docNormal = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'assunto' => 'Documento Novo',
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);

        // 2. Doc warning (SLA warning: 3 dias atrás)
        $docWarning = DocumentoEntrada::create([
            'numero_sequencial' => 2,
            'ano_referencia' => 2026,
            'data_entrada' => now()->subDays(3),
            'assunto' => 'Documento Pendente',
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);

        // 3. Doc critical (SLA critical: 6 dias atrás)
        $docCritical = DocumentoEntrada::create([
            'numero_sequencial' => 3,
            'ano_referencia' => 2026,
            'data_entrada' => now()->subDays(6),
            'assunto' => 'Documento Atrasado',
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);

        // Rodar comando
        Artisan::call('docs:check-sla');

        // Verificar notificações enviadas ao chefe
        Notification::assertSentTo(
            $chefe,
            SimpleBroadcastNotification::class,
            function ($notification) use ($docWarning) {
                return str_contains($notification->toArray($docWarning)['title'], 'Alerta de SLA') &&
                       str_contains($notification->toArray($docWarning)['message'], 'Documento Pendente');
            }
        );

        Notification::assertSentTo(
            $chefe,
            SimpleBroadcastNotification::class,
            function ($notification) use ($docCritical) {
                return str_contains($notification->toArray($docCritical)['title'], 'Alerta Crítico de SLA') &&
                       str_contains($notification->toArray($docCritical)['message'], 'Documento Atrasado');
            }
        );

        // O documento normal não deve disparar alertas
        Notification::assertNotSentTo(
            $chefe,
            SimpleBroadcastNotification::class,
            function ($notification) use ($docNormal) {
                return str_contains($notification->toArray($docNormal)['message'], 'Documento Novo');
            }
        );
    }

    /**
     * F1 — o comando só olhava para 'registrado' e 'recebido'. Um documento
     * acabado de registar nasce em 'pendente_tratamento' e ficava invisível ao
     * SLA justamente na fase em que mais tempo fica parado: à espera de despacho.
     */
    public function test_documento_a_espera_de_despacho_alerta_o_responsavel_do_gabinete(): void
    {
        Notification::fake();
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);
        $respGab = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $gab->update(['responsavel_id' => $respGab->id]);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 10,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(4),
            'assunto' => 'Aguarda despacho do gabinete',
            'departamento_id' => $dep->id,
            'user_id' => $chefe->id,
            'status' => 'pendente_tratamento',
        ]);

        Artisan::call('docs:check-sla');

        // Quem tem de agir é quem despacha, não a chefia do departamento.
        Notification::assertSentTo($respGab, SimpleBroadcastNotification::class);
        Notification::assertNotSentTo($chefe, SimpleBroadcastNotification::class);

        $doc->refresh();
        $this->assertSame('warning', $doc->sla_nivel_notificado);
    }

    /**
     * F1 — um documento encaminhado e nunca recebido não gerava nada: ficava
     * parado entre departamentos sem qualquer alarme.
     */
    public function test_documento_por_receber_alerta_a_chefia_do_destino(): void
    {
        Notification::fake();
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $depOrigem = Departamento::create(['nome' => 'Origem', 'gabinete_id' => $gab->id]);
        $depDestino = Departamento::create(['nome' => 'Destino', 'gabinete_id' => $gab->id]);

        $chefeOrigem = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depOrigem->id]);
        $chefeDestino = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depDestino->id]);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 11,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(10),
            'assunto' => 'Encaminhado e esquecido',
            'departamento_id' => $depOrigem->id,
            'user_id' => $chefeOrigem->id,
            'status' => 'encaminhado',
        ]);

        \App\Models\DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depOrigem->id,
            'destino_departamento_id' => $depDestino->id,
            'usuario_id' => $chefeOrigem->id,
            'encaminhado_em' => now()->subDays(3),
        ]);

        Artisan::call('docs:check-sla');

        Notification::assertSentTo($chefeDestino, SimpleBroadcastNotification::class, function ($n) use ($chefeDestino) {
            return str_contains($n->toArray($chefeDestino)['message'], 'por receber');
        });

        $doc->refresh();
        $this->assertSame('recebimento:warning', $doc->sla_nivel_notificado);
    }

    /**
     * A segunda execução diária não pode repetir o mesmo alerta.
     */
    public function test_segunda_execucao_nao_repete_o_alerta(): void
    {
        Notification::fake();
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);

        DocumentoEntrada::create([
            'numero_sequencial' => 12,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(3),
            'assunto' => 'Pendente no departamento',
            'departamento_id' => $dep->id,
            'user_id' => $chefe->id,
            'status' => 'recebido',
        ]);

        Artisan::call('docs:check-sla');
        Artisan::call('docs:check-sla');

        Notification::assertSentToTimes($chefe, SimpleBroadcastNotification::class, 1);
    }

    /**
     * Sem utilizador com o papel 'chefe-departamento', o comando desistia em
     * silêncio. O responsável do departamento serve de recurso.
     */
    public function test_usa_o_responsavel_do_departamento_quando_nao_ha_chefe_por_papel(): void
    {
        Notification::fake();
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $responsavel = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $dep->update(['responsavel_id' => $responsavel->id]);

        DocumentoEntrada::create([
            'numero_sequencial' => 13,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(6),
            'assunto' => 'Departamento sem chefe por papel',
            'departamento_id' => $dep->id,
            'user_id' => $responsavel->id,
            'status' => 'recebido',
        ]);

        Artisan::call('docs:check-sla');

        Notification::assertSentTo($responsavel, SimpleBroadcastNotification::class);
    }
}
