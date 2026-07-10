<?php

namespace App\Notifications;

use App\Enums\NivelColaboracao;
use App\Models\DocumentoInterno;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notifica um utilizador de que foi convidado para editar colaborativamente um Documento Interno.
 * Entregue in-app (database) e por e-mail (mail), de forma assíncrona.
 */
class ConviteColaboracaoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DocumentoInterno $documento,
        public User $convidadoPor,
        public NivelColaboracao $nivel,
    ) {
        $this->queue = 'notifications';
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('documentos-internos.collab.editor', $this->documento);

        return (new MailMessage)
            ->subject('Convite para editar documento: '.$this->documento->titulo)
            ->greeting('Olá, '.$notifiable->name)
            ->line($this->convidadoPor->name.' convidou-o(a) para colaborar no documento "'.$this->documento->titulo.'".')
            ->line('Nível de acesso: '.$this->nivel->label().'.')
            ->action('Abrir editor colaborativo', $url)
            ->line('Este convite respeita as permissões do seu gabinete.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'convite_colaboracao',
            'documento_interno_id' => $this->documento->id,
            'titulo' => $this->documento->titulo,
            'nivel' => $this->nivel->value,
            'convidado_por' => $this->convidadoPor->name,
            'url' => route('documentos-internos.collab.editor', $this->documento),
            'mensagem' => $this->convidadoPor->name.' convidou-o(a) para editar "'.$this->documento->titulo.'".',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao enviar convite de colaboração', [
            'documento_interno_id' => $this->documento->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
