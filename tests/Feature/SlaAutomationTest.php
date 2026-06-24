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
}
