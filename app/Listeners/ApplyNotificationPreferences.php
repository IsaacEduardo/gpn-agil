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
        // Só se aplica a utilizadores e a canais geríveis.
        if (! $event->notifiable instanceof User) {
            return true;
        }
        if (! NotificationCategory::isManageableChannel($event->channel)) {
            return true;
        }

        $category = NotificationCategory::forType($this->resolveType($event->notification, $event->notifiable));

        return $this->preferences->isEnabled($event->notifiable, $category, $event->channel);
    }

    /**
     * Extrai o 'type' canónico do payload da notificação, quando disponível.
     */
    private function resolveType(object $notification, object $notifiable): ?string
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
}
