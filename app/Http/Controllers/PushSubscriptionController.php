<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $endpoint = $validated['endpoint'];
        $p256dh = data_get($validated, 'keys.p256dh');
        $auth = data_get($validated, 'keys.auth');

        $sub = PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint' => $endpoint],
            ['p256dh' => $p256dh, 'auth' => $auth]
        );

        return response()->json(['ok' => true, 'id' => $sub->id]);
    }
}
