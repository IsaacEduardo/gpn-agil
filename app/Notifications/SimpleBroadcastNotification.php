<?php

namespace App\Notifications;

use App\Notifications\Concerns\CanonicalPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SimpleBroadcastNotification extends Notification
{
    use CanonicalPayload;
    // use Queueable; // Removido para envio síncrono

    protected string $title;

    protected string $message;

    protected ?string $url;

    protected string $priority;

    protected ?string $type;

    public function __construct(string $title = 'Notificação de teste', string $message = 'Olá! Isto é um teste de notificação.', ?string $url = null, string $priority = 'normal', ?string $type = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url ?: url('/notifications');
        $this->priority = $priority;
        $this->type = $type;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->canonicalPayload(
            title: $this->title,
            url: $this->url,
            body: $this->message,
            priority: $this->priority,
            type: $this->type,
            extra: ['message' => $this->message], // BC com leitores/testes existentes
        );
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->canonicalPayload(
            title: $this->title,
            url: $this->url,
            body: $this->message,
            priority: $this->priority,
            type: $this->type,
            extra: ['id' => $this->id, 'message' => $this->message],
        ));
    }
}
