<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\NotificationCategory;

/**
 * Lê e grava as preferências de subscrição de notificações de um utilizador.
 *
 * Modelo opt-out: a ausência de linha significa "ativo". Só os canais geríveis
 * (mail, broadcast) são configuráveis; o canal in-app (database) é sempre mantido.
 */
class NotificationPreferenceService
{
    /**
     * O canal está ativo para esta categoria e utilizador?
     */
    public function isEnabled(User $user, string $category, string $channel): bool
    {
        if (! NotificationCategory::isManageableChannel($channel)) {
            return true; // canais não geríveis (ex.: database) nunca são suprimidos
        }

        $value = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->where('channel', $channel)
            ->value('enabled');

        return $value === null ? true : (bool) $value;
    }

    /**
     * Matriz [categoria][canal] => bool, com o valor por omissão (ativo).
     *
     * @return array<string, array<string, bool>>
     */
    public function matrixFor(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($p) => $p->category.'|'.$p->channel);

        $matrix = [];
        foreach (NotificationCategory::keys() as $category) {
            foreach (array_keys(NotificationCategory::MANAGEABLE_CHANNELS) as $channel) {
                $row = $stored->get($category.'|'.$channel);
                $matrix[$category][$channel] = $row ? (bool) $row->enabled : true;
            }
        }

        return $matrix;
    }

    /**
     * Persiste as preferências a partir de um input de checkboxes.
     * Semântica de checkbox: presente => ativo; ausente => inativo.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public function sync(User $user, array $input): void
    {
        foreach (NotificationCategory::keys() as $category) {
            foreach (array_keys(NotificationCategory::MANAGEABLE_CHANNELS) as $channel) {
                $enabled = (bool) data_get($input, $category.'.'.$channel, false);

                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'category' => $category, 'channel' => $channel],
                    ['enabled' => $enabled],
                );
            }
        }
    }
}
