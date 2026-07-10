<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\NotificationUserSetting;
use App\Models\User;
use App\Notifications\NotificationCategory;
use Illuminate\Support\Carbon;

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

    /**
     * Definições gerais do utilizador (quiet hours + digest), com valores por omissão.
     */
    public function settings(User $user): NotificationUserSetting
    {
        $setting = NotificationUserSetting::firstOrNew(['user_id' => $user->id]);

        if (! $setting->exists) {
            $setting->quiet_hours_enabled = false;
            $setting->quiet_start = '22:00:00';
            $setting->quiet_end = '07:00:00';
            $setting->timezone = config('app.timezone');
            $setting->digest_enabled = false;
        }

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(User $user, array $data): void
    {
        NotificationUserSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'quiet_hours_enabled' => (bool) data_get($data, 'quiet_hours_enabled', false),
                'quiet_start' => data_get($data, 'quiet_start') ?: '22:00:00',
                'quiet_end' => data_get($data, 'quiet_end') ?: '07:00:00',
                'timezone' => data_get($data, 'timezone') ?: config('app.timezone'),
                'digest_enabled' => (bool) data_get($data, 'digest_enabled', false),
            ],
        );
    }

    public function digestEnabled(User $user): bool
    {
        return (bool) $this->settings($user)->digest_enabled;
    }

    /**
     * O momento indicado cai dentro do período de silêncio do utilizador?
     * Suporta janelas que atravessam a meia-noite (ex.: 22:00–07:00).
     */
    public function isWithinQuietHours(User $user, ?Carbon $now = null): bool
    {
        $setting = $this->settings($user);
        if (! $setting->quiet_hours_enabled) {
            return false;
        }

        $tz = $setting->timezone ?: config('app.timezone');
        $current = ($now ? $now->copy() : Carbon::now())->setTimezone($tz)->format('H:i');
        $start = substr((string) $setting->quiet_start, 0, 5);
        $end = substr((string) $setting->quiet_end, 0, 5);

        if ($start === $end) {
            return false;
        }

        // Janela normal (mesmo dia) vs. janela que atravessa a meia-noite.
        return $start < $end
            ? ($current >= $start && $current < $end)
            : ($current >= $start || $current < $end);
    }
}
