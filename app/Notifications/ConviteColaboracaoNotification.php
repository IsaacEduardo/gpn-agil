<?php

namespace App\Notifications;

use App\Enums\NivelColaboracao;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Notifications\Concerns\CanonicalPayload;
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
    use CanonicalPayload, Queueable;

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
        return $this->canonicalPayload(
            title: 'Convite para editar "'.$this->documento->titulo.'"',
            url: route('documentos-internos.collab.editor', $this->documento),
            body: $this->convidadoPor->name.' convidou-o(a) para editar "'.$this->documento->titulo.'".',
            priority: 'normal',
            type: 'convite_colaboracao',
            icon: 'fas fa-user-edit',
            extra: [
                'documento_interno_id' => $this->documento->id,
                'nivel' => $this->nivel->value,
                'convidado_por' => $this->convidadoPor->name,
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao enviar convite de colaboração', [
            'documento_interno_id' => $this->documento->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
