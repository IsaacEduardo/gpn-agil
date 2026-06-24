<?php

namespace App\Console\Commands;

use App\Models\DocumentoEntrada;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica o SLA dos documentos de entrada pendentes e notifica os chefes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando verificação de SLA de documentos...');
        Log::info('docs:check-sla iniciado.');

        $docs = DocumentoEntrada::with(['departamento.chefe'])
            ->whereIn('status', ['registrado', 'recebido'])
            ->where('arquivado', false)
            ->get();

        $notifiedCount = 0;

        foreach ($docs as $doc) {
            $statusSla = $doc->sla_status;
            $dias = $doc->dias_decorridos;

            if ($statusSla === 'normal') {
                continue;
            }

            $departamento = $doc->departamento;
            if (! $departamento) {
                continue;
            }

            $chefe = $departamento->chefe;
            if (! $chefe) {
                // Se não houver chefe definido, logamos a informação
                $this->warn("Sem chefe definido para o departamento '{$departamento->nome}' (Doc ID: {$doc->id})");

                continue;
            }

            $prefix = $statusSla === 'critical' ? 'Alerta Crítico de SLA' : 'Alerta de SLA';
            $urgency = $statusSla === 'critical' ? '(Crítico)' : '(Atenção)';

            $title = "{$prefix}: Documento #{$doc->numero_sequencial}/{$doc->ano_referencia}";
            $message = "O documento '{$doc->assunto}' está pendente no departamento há {$dias} dias {$urgency}.";
            $url = route('documentos-entradas.show', $doc->id);

            try {
                $chefe->notify(new SimpleBroadcastNotification($title, $message, $url));
                $notifiedCount++;
                $this->line("Notificado chefe {$chefe->name} do departamento '{$departamento->nome}' sobre o documento #{$doc->numero_sequencial}/{$doc->ano_referencia} ({$dias} dias)");
            } catch (\Throwable $e) {
                $this->error("Erro ao notificar chefe ID {$chefe->id} sobre o documento ID {$doc->id}: ".$e->getMessage());
                Log::error('Erro docs:check-sla: '.$e->getMessage());
            }
        }

        $this->info("Verificação concluída. Chefes notificados: {$notifiedCount}");
        Log::info("docs:check-sla concluído. Notificações enviadas: {$notifiedCount}");

        return Command::SUCCESS;
    }
}
