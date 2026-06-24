<?php

namespace App\Notifications;

use App\Models\DocumentoTarefa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notificação enviada por e-mail de forma assíncrona quando uma tarefa é delegada.
 */
class TarefaDelegadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * A instância da tarefa associada.
     */
    public DocumentoTarefa $tarefa;

    /**
     * Cria uma nova instância de notificação.
     *
     * @param  DocumentoTarefa  $tarefa
     */
    public function __construct(DocumentoTarefa $tarefa)
    {
        $this->tarefa = $tarefa;
        $this->queue = 'notifications';
    }

    /**
     * Obter os canais de entrega da notificação.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Obter a representação por e-mail da notificação.
     *
     * @param  object  $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $documento = $this->tarefa->documento;
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);

        return (new MailMessage)
            ->subject("Nova Tarefa Designada: {$this->tarefa->titulo}")
            ->markdown('emails.tarefas.delegada', [
                'tarefa' => $this->tarefa,
                'documento' => $documento,
                'numero' => $numero,
                'url' => $url,
                'notifiable' => $notifiable,
            ]);
    }

    /**
     * Tratar falha no processamento do envio por fila da notificação.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao enviar notificação de e-mail de tarefa delegada', [
            'tarefa_id' => $this->tarefa->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
