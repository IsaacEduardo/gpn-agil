<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Jobs\CheckRetentionPolicy;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\RetentionSchedule;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RetentionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_vencido_notifica_o_chefe_uma_vez(): void
    {
        Notification::fake();

        $gab = Gabinete::create(['nome' => 'Gabinete R']);
        $dep = Departamento::create(['nome' => 'Dep R', 'sigla' => 'DR', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['name' => 'Chefe R', 'departamento_id' => $dep->id]);
        $dep->responsavel_id = $chefe->id;
        $dep->save();

        $especie = DocumentoEspecie::create(['nome' => 'CIRCULAR', 'descricao' => 'Circular', 'ativo' => true]);
        RetentionSchedule::create([
            'documento_especie_id' => $especie->id,
            'temporalidade_anos' => 1,
            'acao_final' => 'eliminar',
        ]);

        $doc = DocumentoInterno::create([
            'titulo' => 'Circular Antiga',
            'conteudo_final' => '<p>x</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $chefe->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'CIR/001',
        ]);
        // Envelhecer o documento para ultrapassar a temporalidade (1 ano).
        DB::table('documento_internos')->where('id', $doc->id)->update(['created_at' => now()->subYears(2)]);

        (new CheckRetentionPolicy)->handle();

        Notification::assertSentToTimes($chefe, SimpleBroadcastNotification::class, 1);
        Notification::assertSentTo($chefe, SimpleBroadcastNotification::class, function ($n) use ($chefe) {
            return $n->toArray($chefe)['type'] === 'retencao';
        });
        $this->assertNotNull($doc->fresh()->retencao_notificada_em);

        // Segunda execução não deve re-notificar (idempotência).
        (new CheckRetentionPolicy)->handle();
        Notification::assertSentToTimes($chefe, SimpleBroadcastNotification::class, 1);
    }
}
