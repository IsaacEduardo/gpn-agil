<?php

namespace Tests\Unit;

use App\Jobs\CheckRetentionPolicy;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\RetentionSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckRetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerta_documentos_com_temporalidade_vencida(): void
    {
        $especie = DocumentoEspecie::factory()->create();

        RetentionSchedule::create([
            'documento_especie_id' => $especie->id,
            'temporalidade_anos' => 1,
            'acao_final' => 'eliminar',
        ]);

        $vencido = DocumentoInterno::factory()->create(['documento_especie_id' => $especie->id]);
        $vencido->forceFill(['created_at' => now()->subYears(2)])->saveQuietly();

        $recente = DocumentoInterno::factory()->create(['documento_especie_id' => $especie->id]);

        Log::spy();

        (new CheckRetentionPolicy)->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, "Documento ID {$vencido->id}"))
            ->once();

        Log::shouldNotHaveReceived('warning', [
            \Mockery::on(fn ($msg) => str_contains($msg, "Documento ID {$recente->id}")),
        ]);
    }

    public function test_corre_sem_erro_quando_nao_ha_regras(): void
    {
        (new CheckRetentionPolicy)->handle();

        $this->assertDatabaseCount('retention_schedules', 0);
    }
}
