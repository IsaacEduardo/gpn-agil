<?php

namespace App\Jobs;

use App\Models\DocumentoInterno;
use App\Models\RetentionSchedule;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckRetentionPolicy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando verificação de Tabela de Temporalidade...');

        $schedules = RetentionSchedule::all();

        foreach ($schedules as $schedule) {
            $cutoffDate = now()->subYears($schedule->temporalidade_anos);

            // Encontrar documentos vencidos
            // Focando em DocumentoInterno por enquanto
            $expiredDocs = DocumentoInterno::where('documento_especie_id', $schedule->documento_especie_id)
                ->where('created_at', '<', $cutoffDate)
                ->where('status', '!=', 'arquivado')
                ->where('status', '!=', 'eliminado')
                ->get();

            if ($expiredDocs->count() > 0) {
                Log::info("Encontrados {$expiredDocs->count()} documentos vencidos para a espécie ID {$schedule->documento_especie_id}. Ação prevista: {$schedule->acao_final}");

                foreach ($expiredDocs as $doc) {
                    $vencimento = $doc->created_at->addYears($schedule->temporalidade_anos)->format('d/m/Y');
                    Log::warning("Documento ID {$doc->id} ({$doc->titulo}) venceu em {$vencimento}. Ação: {$schedule->acao_final}");

                    // Idempotência: notificar o chefe apenas uma vez por documento.
                    if ($doc->retencao_notificada_em !== null) {
                        continue;
                    }

                    $chefe = $doc->departamento?->chefe;
                    if (! $chefe) {
                        continue;
                    }

                    $chefe->notify(new SimpleBroadcastNotification(
                        'Temporalidade atingida: '.$doc->titulo,
                        'O documento venceu o prazo de retenção em '.$vencimento.'. Ação prevista: '.$schedule->acao_final.'.',
                        route('documentos-internos.show', $doc->id),
                        'high',
                        'retencao',
                    ));

                    $doc->retencao_notificada_em = now();
                    $doc->save();
                }
            }
        }

        Log::info('Verificação de temporalidade concluída.');
    }
}
