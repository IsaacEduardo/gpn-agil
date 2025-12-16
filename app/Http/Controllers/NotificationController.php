<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(10)->get()->map(function (DatabaseNotification $n) {
            $data = $n->data ?? [];

            return [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at->toIso8601String(),
                'title' => $data['title'] ?? ($data['message'] ?? 'Nova notificação'),
                'acao' => $data['acao'] ?? null,
                'url' => $data['url'] ?? null,
            ];
        });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function page(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json(['message' => 'Notificação não encontrada'], 404);
        }
        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}
