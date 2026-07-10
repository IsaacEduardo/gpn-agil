<?php

namespace App\Notifications;

use App\Models\Requisicao;
use App\Models\User;
use App\Notifications\Concerns\CanonicalPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisicaoAssinada extends Notification
{
    use CanonicalPayload, Queueable;

    protected $requisicao;

    protected $signer;

    /**
     * Create a new notification instance.
     */
    public function __construct(Requisicao $requisicao, User $signer)
    {
        $this->requisicao = $requisicao;
        $this->signer = $signer;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Implementar se necessário e-mail
        return (new MailMessage)
            ->subject('Requisição Assinada Digitalmente')
            ->line('Sua requisição foi assinada digitalmente.')
            ->action('Ver Requisição', route('requisicoes.show', $this->requisicao->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->canonicalPayload(
            title: 'Requisição Assinada',
            url: route('requisicoes.show', $this->requisicao->id),
            body: "A requisição #{$this->requisicao->codigo_sequencial} foi assinada por {$this->signer->name}.",
            priority: 'normal',
            type: 'requisicao_assinada',
            icon: 'fas fa-file-signature',
            extra: [
                'requisicao_id' => $this->requisicao->id,
                'color' => 'success',
            ],
        );
    }
}
