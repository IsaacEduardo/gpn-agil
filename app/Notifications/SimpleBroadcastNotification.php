<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SimpleBroadcastNotification extends Notification
{
    // use Queueable; // Removido para envio síncrono

    protected string $title;

    protected string $message;

    protected ?string $url;

    public function __construct(string $title = 'Notificação de teste', string $message = 'Olá! Isto é um teste de notificação.', ?string $url = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url ?: url('/notifications');
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
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ]);
    }
}
