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
        $matrix = $this->preferences->matrixFor($request->user());

        return view('notifications.preferences', [
            'matrix' => $matrix,
            'categories' => NotificationCategory::CATEGORIES,
            'channels' => NotificationCategory::MANAGEABLE_CHANNELS,
        ]);
    }

    public function update(Request $request)
    {
        $this->preferences->sync($request->user(), $request->input('prefs', []));

        return redirect()
            ->route('notifications.preferences.edit')
            ->with('success', 'Preferências de notificação atualizadas.');
    }
}
