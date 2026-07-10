<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationSlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_critico_ha_muito_tempo_escala_para_o_gabinete_uma_vez(): void
    {
        Notification::fake();

        $gab = Gabinete::create(['nome' => 'Gabinete E', 'sigla' => 'GE']);
        $dep = Departamento::create(['nome' => 'Dep E', 'sigla' => 'DE', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['name' => 'Chefe E', 'departamento_id' => $dep->id]);
        $dep->responsavel_id = $chefe->id;
        $dep->save();

        $responsavelGab = User::factory()->create(['name' => 'Responsável Gab']);
        $gab->responsavel_id = $responsavelGab->id;
        $gab->save();

        // Documento crítico há 9 dias (>= limiar de escalonamento de 8).
        DocumentoEntrada::create([
            'numero_sequencial' => 7,
            'ano_referencia' => 2026,
            'data_entrada' => now()->subDays(9),
            'assunto' => 'Processo urgente parado',
            'departamento_id' => $dep->id,
            'user_id' => $chefe->id,
            'status' => 'registrado',
        ]);

        Artisan::call('docs:check-sla');

        // O responsável do gabinete recebe a notificação de escalonamento.
        Notification::assertSentTo(
            $responsavelGab,
            SimpleBroadcastNotification::class,
            function ($n) use ($responsavelGab) {
                $data = $n->toArray($responsavelGab);

                return $data['type'] === 'sla_critical'
                    && $data['priority'] === 'urgent'
                    && str_contains($data['title'], 'Escalonamento');
            }
        );

        // Segunda execução não re-escala (idempotência via sla_escalado_em).
        Artisan::call('docs:check-sla');
        Notification::assertSentToTimes($responsavelGab, SimpleBroadcastNotification::class, 1);
    }
}
