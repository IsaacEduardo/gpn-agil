<?php

namespace App\Notifications;

use App\Models\DocumentoTarefa;
use App\Notifications\Concerns\CanonicalPayload;
use App\Notifications\Concerns\QueuedRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Notificação de tarefa delegada, entregue de forma assíncrona in-app (database),
 * em tempo real (broadcast) e por e-mail (apenas para destinatários com e-mail).
 */
class TarefaDelegadaNotification extends Notification implements ShouldQueue
{
    use CanonicalPayload, Queueable, QueuedRetryPolicy;

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
        $channels = ['database', 'broadcast'];
        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Representação canónica in-app da notificação.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $documento = $this->tarefa->documento;
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $prazo = $this->tarefa->prazo_at ? Carbon::parse($this->tarefa->prazo_at)->format('d/m/Y') : null;

        return $this->canonicalPayload(
            title: 'Nova tarefa: '.$this->tarefa->titulo,
            url: route('documentos-entradas.show', $documento->id),
            body: 'No documento '.$numero.($prazo ? ' · prazo '.$prazo : ''),
            priority: 'high',
            type: 'tarefa_delegada',
            icon: 'fas fa-tasks',
            extra: [
                'tarefa_id' => $this->tarefa->id,
                'documento_id' => $documento->id,
                'numero' => $numero,
            ],
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + [
            'broadcasted_at' => now()->toDateTimeString(),
        ]);
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
