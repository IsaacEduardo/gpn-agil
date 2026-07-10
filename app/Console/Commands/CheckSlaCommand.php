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
     * Nº de dias decorridos a partir do qual um documento crítico é escalado
     * para o responsável do gabinete.
     */
    private const DIAS_ESCALONAMENTO = 8;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando verificação de SLA de documentos...');
        Log::info('docs:check-sla iniciado.');

        $docs = DocumentoEntrada::with(['departamento.chefe', 'departamento.gabinete.responsavel'])
            ->whereIn('status', ['registrado', 'recebido'])
            ->where('arquivado', false)
            ->get();

        $notifiedCount = 0;

        foreach ($docs as $doc) {
            $statusSla = $doc->sla_status;
            $dias = $doc->dias_decorridos;

            if ($statusSla === 'normal') {
                // Re-armar: se o documento já esteve em alerta, limpar a marca para
                // permitir nova notificação caso volte a violar o SLA no futuro.
                if ($doc->sla_nivel_notificado !== null) {
                    $doc->sla_nivel_notificado = null;
                    $doc->save();
                }

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

            // Escalonamento: documentos críticos há muito tempo sobem, uma única vez,
            // para o responsável do gabinete (independentemente da notificação ao chefe).
            if ($statusSla === 'critical' && $dias >= self::DIAS_ESCALONAMENTO && $doc->sla_escalado_em === null) {
                $responsavel = optional($departamento->gabinete)->responsavel;
                if ($responsavel && $responsavel->id !== $chefe->id) {
                    try {
                        $responsavel->notify(new SimpleBroadcastNotification(
                            "Escalonamento de SLA: Documento #{$doc->numero_sequencial}/{$doc->ano_referencia}",
                            "O documento '{$doc->assunto}' está crítico há {$dias} dias sem resolução no departamento '{$departamento->nome}'.",
                            route('documentos-entradas.show', $doc->id),
                            'urgent',
                            'sla_critical',
                        ));
                        $doc->sla_escalado_em = now();
                        $doc->save();
                        $this->line("Escalonado ao responsável do gabinete: doc #{$doc->numero_sequencial}/{$doc->ano_referencia} ({$dias} dias)");
                    } catch (\Throwable $e) {
                        $this->error("Erro ao escalar documento ID {$doc->id}: ".$e->getMessage());
                        Log::error('Erro escalonamento docs:check-sla: '.$e->getMessage());
                    }
                }
            }

            // Idempotência: não repetir a notificação enquanto o documento permanecer
            // no mesmo nível de SLA já comunicado. Uma escalada (warning -> critical)
            // ainda gera nova notificação, porque o nível muda.
            if ($doc->sla_nivel_notificado === $statusSla) {
                continue;
            }

            $prefix = $statusSla === 'critical' ? 'Alerta Crítico de SLA' : 'Alerta de SLA';
            $urgency = $statusSla === 'critical' ? '(Crítico)' : '(Atenção)';
            $priority = $statusSla === 'critical' ? 'urgent' : 'high';

            $title = "{$prefix}: Documento #{$doc->numero_sequencial}/{$doc->ano_referencia}";
            $message = "O documento '{$doc->assunto}' está pendente no departamento há {$dias} dias {$urgency}.";
            $url = route('documentos-entradas.show', $doc->id);

            try {
                $chefe->notify(new SimpleBroadcastNotification($title, $message, $url, $priority, 'sla_'.$statusSla));
                $doc->sla_nivel_notificado = $statusSla;
                $doc->save();
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
