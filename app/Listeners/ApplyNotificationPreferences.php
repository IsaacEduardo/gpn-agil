<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NotificationCategory;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Aplica as preferências de subscrição do utilizador antes de cada envio.
 *
 * O evento NotificationSending é disparado por canal; devolver `false` cancela
 * apenas esse canal, deixando os restantes intactos. O canal in-app (database)
 * nunca é suprimido, garantindo o registo da notificação.
 */
class ApplyNotificationPreferences
{
    public function __construct(private NotificationPreferenceService $preferences)
    {
    }

    public function handle(NotificationSending $event): bool
    {
        // Só se aplica a utilizadores e a canais geríveis (o in-app nunca é suprimido).
        if (! $event->notifiable instanceof User) {
            return true;
        }
        if (! NotificationCategory::isManageableChannel($event->channel)) {
            return true;
        }

        $payload = $this->payload($event->notification, $event->notifiable);
        $category = NotificationCategory::forType($payload['type'] ?? null);
        $priority = $payload['priority'] ?? 'normal';

        // 1) Preferência explícita por categoria/canal (opt-out).
        if (! $this->preferences->isEnabled($event->notifiable, $category, $event->channel)) {
            return false;
        }

        // 2) Quiet hours: fora de urgências, silenciar mail/broadcast no período de silêncio.
        //    O canal in-app continua a registar (nunca chega aqui, por não ser gerível).
        if ($priority !== 'urgent' && $this->preferences->isWithinQuietHours($event->notifiable)) {
            return false;
        }

        return true;
    }

    /**
     * Extrai o payload canónico (type/priority) da notificação, quando disponível.
     *
     * @return array<string, mixed>
     */
    private function payload(object $notification, object $notifiable): array
    {
        if (! method_exists($notification, 'toArray')) {
            return [];
        }

        try {
            $data = $notification->toArray($notifiable);

            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
