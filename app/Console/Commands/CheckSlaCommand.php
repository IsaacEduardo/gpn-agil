<?php

namespace App\Console\Commands;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoEntrada;
use App\Models\User;
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
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $description = 'Verifica o SLA dos documentos de entrada pendentes e notifica quem tem de agir';

    /**
     * Nº de dias decorridos a partir do qual um documento crítico é escalado
     * para o responsável do gabinete.
     */
    private const DIAS_ESCALONAMENTO = 8;

    /**
     * Dias a partir dos quais um encaminhamento por receber gera alerta. O
     * relógio aqui é o do encaminhamento, não o da entrada do documento.
     */
    private const DIAS_AVISO_RECEBIMENTO = 2;

    private const DIAS_CRITICO_RECEBIMENTO = 5;

    /**
     * As duas pistas de alerta (tratamento e recebimento) partilham a coluna
     * sla_nivel_notificado. O prefixo evita que uma marque a outra como já
     * notificada — um documento que foi avisado em 'warning' enquanto estava no
     * departamento voltaria a ser silenciado depois de encaminhado.
     */
    private const PREFIXO_RECEBIMENTO = 'recebimento:';

    /**
     * Estados em que um documento ainda está em curso e, portanto, sujeito a SLA.
     * 'pendente_tratamento' é o estado em que o registo nasce e é onde o
     * documento fica mais tempo parado — à espera de despacho.
     */
    private function estadosEmCurso(): array
    {
        return [
            DocumentoStatus::PENDENTE_TRATAMENTO->value,
            DocumentoStatus::REGISTRADO->value,
            DocumentoStatus::ENCAMINHADO->value,
            DocumentoStatus::RECEBIDO->value,
        ];
    }

    public function handle(): int
    {
        $this->info('Iniciando verificação de SLA de documentos...');
        Log::info('docs:check-sla iniciado.');

        $docs = DocumentoEntrada::with([
            'departamento.chefe',
            'departamento.gabinete.responsavel',
            'encaminhamentos' => fn ($q) => $q->whereNull('recebido_em')->orderByDesc('encaminhado_em'),
            'encaminhamentos.destinoDepartamento.chefe',
        ])
            ->whereIn('status', $this->estadosEmCurso())
            ->where('arquivado', false)
            ->get();

        $notifiedCount = 0;

        foreach ($docs as $doc) {
            $notificou = $doc->status === DocumentoStatus::ENCAMINHADO->value
                ? $this->verificarRecebimentoPendente($doc)
                : $this->verificarTratamento($doc);

            if ($notificou) {
                $notifiedCount++;
            }
        }

        $this->info("Verificação concluída. Notificações enviadas: {$notifiedCount}");
        Log::info("docs:check-sla concluído. Notificações enviadas: {$notifiedCount}");

        return Command::SUCCESS;
    }

    /**
     * Documento parado à espera de tratamento: alerta quem tem de agir sobre ele.
     */
    private function verificarTratamento(DocumentoEntrada $doc): bool
    {
        $statusSla = $doc->sla_status;
        $dias = $doc->dias_decorridos;

        if ($statusSla === 'normal') {
            $this->rearmar($doc);

            return false;
        }

        $departamento = $doc->departamento;
        if (! $departamento) {
            return false;
        }

        $responsavelGabinete = optional($departamento->gabinete)->responsavel;

        // Em 'pendente_tratamento' quem tem de agir é quem despacha, não a
        // chefia do departamento de destino — o documento ainda não lá chegou.
        $destinatario = $doc->status === DocumentoStatus::PENDENTE_TRATAMENTO->value
            ? $responsavelGabinete
            : $departamento->chefe;

        if (! $destinatario) {
            $this->warn("Sem destinatário de SLA para o departamento '{$departamento->nome}' (Doc ID: {$doc->id})");

            return false;
        }

        $this->escalarSeNecessario($doc, $statusSla, $dias, $destinatario, $responsavelGabinete);

        // Idempotência: não repetir enquanto o documento ficar no mesmo nível.
        // Uma subida warning -> critical ainda notifica, porque o nível muda.
        if ($doc->sla_nivel_notificado === $statusSla) {
            return false;
        }

        $prefixo = $statusSla === 'critical' ? 'Alerta Crítico de SLA' : 'Alerta de SLA';
        $urgencia = $statusSla === 'critical' ? '(Crítico)' : '(Atenção)';
        $prioridade = $statusSla === 'critical' ? 'urgent' : 'high';

        $onde = $doc->status === DocumentoStatus::PENDENTE_TRATAMENTO->value
            ? 'aguarda despacho do gabinete'
            : "está pendente no departamento '{$departamento->nome}'";

        $titulo = "{$prefixo}: Documento #{$doc->numero_sequencial}/{$doc->ano_referencia}";
        $mensagem = "O documento '{$doc->assunto}' {$onde} há {$dias} dias {$urgencia}.";

        if (! $this->notificar($destinatario, $doc, $titulo, $mensagem, $prioridade, 'sla_'.$statusSla)) {
            return false;
        }

        $doc->sla_nivel_notificado = $statusSla;
        $doc->save();

        $this->line("Notificado {$destinatario->name} sobre o documento #{$doc->numero_sequencial}/{$doc->ano_referencia} ({$dias} dias)");

        return true;
    }

    /**
     * Documento encaminhado e nunca recebido: fica parado entre departamentos
     * sem que nada o assinale. Alerta a chefia do destino.
     */
    private function verificarRecebimentoPendente(DocumentoEntrada $doc): bool
    {
        $enc = $doc->encaminhamentos->first();

        // Estado 'encaminhado' sem pendência (por exemplo, cancelado entretanto):
        // limpa a marca para não silenciar um alerta futuro.
        if (! $enc || ! $enc->encaminhado_em) {
            $this->rearmar($doc, self::PREFIXO_RECEBIMENTO);

            return false;
        }

        $dias = (int) $enc->encaminhado_em->diffInDays(now());

        $nivel = match (true) {
            $dias >= self::DIAS_CRITICO_RECEBIMENTO => 'critical',
            $dias >= self::DIAS_AVISO_RECEBIMENTO => 'warning',
            default => 'normal',
        };

        if ($nivel === 'normal') {
            $this->rearmar($doc, self::PREFIXO_RECEBIMENTO);

            return false;
        }

        $destino = $enc->destinoDepartamento;
        $destinatario = optional($destino)->chefe;

        if (! $destinatario) {
            $this->warn("Sem destinatário para recebimento pendente (Doc ID: {$doc->id})");

            return false;
        }

        $marcador = self::PREFIXO_RECEBIMENTO.$nivel;
        if ($doc->sla_nivel_notificado === $marcador) {
            return false;
        }

        $prioridade = $nivel === 'critical' ? 'urgent' : 'high';
        $titulo = "Documento #{$doc->numero_sequencial}/{$doc->ano_referencia} por receber";
        $mensagem = "O documento '{$doc->assunto}' está por receber no departamento '"
            .(optional($destino)->nome ?? 'destino')."' há {$dias} dias.";

        if (! $this->notificar($destinatario, $doc, $titulo, $mensagem, $prioridade, 'sla_'.$nivel)) {
            return false;
        }

        $doc->sla_nivel_notificado = $marcador;
        $doc->save();

        $this->line("Notificado {$destinatario->name} sobre recebimento pendente do documento #{$doc->numero_sequencial}/{$doc->ano_referencia} ({$dias} dias)");

        return true;
    }

    /**
     * Documento crítico há muito tempo sobe, uma única vez, ao responsável do
     * gabinete — independentemente da notificação ao destinatário habitual.
     */
    private function escalarSeNecessario(
        DocumentoEntrada $doc,
        string $statusSla,
        int $dias,
        User $destinatario,
        ?User $responsavelGabinete,
    ): void {
        if ($statusSla !== 'critical' || $dias < self::DIAS_ESCALONAMENTO || $doc->sla_escalado_em !== null) {
            return;
        }

        if (! $responsavelGabinete || (int) $responsavelGabinete->id === (int) $destinatario->id) {
            return;
        }

        $enviou = $this->notificar(
            $responsavelGabinete,
            $doc,
            "Escalonamento de SLA: Documento #{$doc->numero_sequencial}/{$doc->ano_referencia}",
            "O documento '{$doc->assunto}' está crítico há {$dias} dias sem resolução.",
            'urgent',
            'sla_critical',
        );

        if ($enviou) {
            $doc->sla_escalado_em = now();
            $doc->save();
            $this->line("Escalonado ao responsável do gabinete: doc #{$doc->numero_sequencial}/{$doc->ano_referencia} ({$dias} dias)");
        }
    }

    /**
     * Limpa a marca de nível já notificado para que um regresso à violação do
     * SLA volte a alertar. O prefixo restringe a limpeza à pista em causa.
     */
    private function rearmar(DocumentoEntrada $doc, string $prefixo = ''): void
    {
        $marca = $doc->sla_nivel_notificado;
        if ($marca === null) {
            return;
        }

        $pertenceAEstaPista = $prefixo === ''
            ? ! str_starts_with($marca, self::PREFIXO_RECEBIMENTO)
            : str_starts_with($marca, $prefixo);

        if (! $pertenceAEstaPista) {
            return;
        }

        $doc->sla_nivel_notificado = null;
        $doc->save();
    }

    /**
     * Uma falha de notificação nunca interrompe a verificação dos restantes
     * documentos.
     */
    private function notificar(User $destinatario, DocumentoEntrada $doc, string $titulo, string $mensagem, string $prioridade, string $tipo): bool
    {
        try {
            $destinatario->notify(new SimpleBroadcastNotification(
                $titulo,
                $mensagem,
                route('documentos-entradas.show', $doc->id),
                $prioridade,
                $tipo,
            ));

            return true;
        } catch (\Throwable $e) {
            $this->error("Erro ao notificar o utilizador ID {$destinatario->id} sobre o documento ID {$doc->id}: ".$e->getMessage());
            Log::error('Erro docs:check-sla: '.$e->getMessage());

            return false;
        }
    }
}
