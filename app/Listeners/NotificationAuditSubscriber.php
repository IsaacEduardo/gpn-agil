<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Regista a trilha de auditoria de entrega de notificações (enviado/falhado)
 * por canal, ouvindo os eventos nativos do sistema de notificações.
 */
class NotificationAuditSubscriber
{
    public function handleSent(NotificationSent $event): void
    {
        $this->record($event->notifiable, $event->notification, $event->channel, 'sent', null);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $reason = null;
        if (! empty($event->data)) {
            $reason = is_string($event->data) ? $event->data : json_encode($event->data);
        }

        $this->record($event->notifiable, $event->notification, $event->channel, 'failed', $reason);
    }

    private function record(object $notifiable, object $notification, string $channel, string $status, ?string $response): void
    {
        try {
            NotificationDelivery::create([
                'notification_id' => $notification->id ?? null,
                'notifiable_type' => method_exists($notifiable, 'getMorphClass') ? $notifiable->getMorphClass() : get_class($notifiable),
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification_type' => get_class($notification),
                'event_type' => $this->resolveEventType($notification, $notifiable),
                'channel' => $channel,
                'status' => $status,
                'response' => $response ? mb_substr($response, 0, 2000) : null,
            ]);
        } catch (\Throwable $e) {
            // A auditoria nunca deve quebrar o envio da notificação.
            Log::warning('Falha ao registar auditoria de entrega de notificação', [
                'error' => $e->getMessage(),
                'channel' => $channel,
                'status' => $status,
            ]);
        }
    }

    /**
     * Extrai o 'type' canónico do payload da notificação, quando disponível.
     */
    private function resolveEventType(object $notification, object $notifiable): ?string
    {
        if (! method_exists($notification, 'toArray')) {
            return null;
        }

        try {
            $data = $notification->toArray($notifiable);

            return is_array($data) ? ($data['type'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            NotificationSent::class => 'handleSent',
            NotificationFailed::class => 'handleFailed',
        ];
    }
}
