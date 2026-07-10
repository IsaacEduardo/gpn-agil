<?php

namespace App\Http\Controllers;

use App\Notifications\NotificationCategory;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private NotificationPreferenceService $preferences)
    {
    }

    public function edit(Request $request)
    {
        $user = $request->user();

        return view('notifications.preferences', [
            'matrix' => $this->preferences->matrixFor($user),
            'categories' => NotificationCategory::CATEGORIES,
            'channels' => NotificationCategory::MANAGEABLE_CHANNELS,
            'settings' => $this->preferences->settings($user),
            'timezones' => $this->timezoneOptions(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'quiet_start' => ['nullable', 'date_format:H:i'],
            'quiet_end' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user = $request->user();
        $this->preferences->sync($user, $request->input('prefs', []));
        $this->preferences->saveSettings($user, [
            'quiet_hours_enabled' => $request->boolean('quiet_hours_enabled'),
            'quiet_start' => $validated['quiet_start'] ?? null,
            'quiet_end' => $validated['quiet_end'] ?? null,
            'timezone' => $validated['timezone'] ?? null,
            'digest_enabled' => $request->boolean('digest_enabled'),
        ]);

        return redirect()
            ->route('notifications.preferences.edit')
            ->with('success', 'Preferências de notificação atualizadas.');
    }

    /**
     * @return array<int, string>
     */
    private function timezoneOptions(): array
    {
        return array_values(array_unique(array_filter([
            config('app.timezone'),
            'Africa/Luanda',
            'UTC',
            'Europe/Lisbon',
        ])));
    }
}
