<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Endpoint para notificações:
     * - Se chamado via AJAX / API (Accept: application/json ou X-Requested-With: XMLHttpRequest), retorna JSON para a Central / Dropdown.
     * - Se acessado diretamente no navegador pelo utilizador (GET /notifications ou /notificacoes), renderiza a página Blade.
     */
    public function index(Request $request)
    {
        // Se a requisição for do navegador (HTML), renderiza a página de notificações
        if (! $request->wantsJson() && ! $request->ajax() && ! $request->is('api/*')) {
            return $this->page($request);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['unread_count' => 0, 'notifications' => []], 401);
        }

        $filter = $request->query('filter', 'all'); // 'all', 'workflow', 'system'
        $limit = (int) $request->query('limit', 15);

        $query = $user->notifications()->latest();

        $allNotifications = $query->limit($limit)->get();

        $presentedList = $allNotifications->map(function (DatabaseNotification $n) {
            $data = $n->data ?? [];
            $p = NotificationPresenter::present($data);

            return [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'is_read' => ! is_null($n->read_at),
                'created_at' => $n->created_at->toIso8601String(),
                'created_at_human' => $n->created_at->diffForHumans(),
                'data' => $data,
                'title' => $p['title'],
                'body' => $p['body'],
                'url' => $p['url'],
                'priority' => $p['priority'],
                'type' => $p['type'],
                'category' => $p['category'],
                'icon' => $p['icon'],
                'icon_bg' => $p['icon_bg'],
                'priority_color' => $p['priority_color'],
                'acao' => $data['acao'] ?? $p['type'],
            ];
        });

        // Filtrar em memória conforme aba selecionada se não for 'all'
        $filtered = $filter === 'all'
            ? $presentedList
            : $presentedList->filter(fn ($item) => $item['category'] === $filter)->values();

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $unreadCount,
            'filter' => $filter,
            'notifications' => $filtered,
        ]);
    }

    /**
     * Exibe a página completa de histórico de notificações.
     */
    public function page(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = $user->notifications()->latest();

        $notifications = $query->paginate(25);

        if ($filter !== 'all') {
            $filteredItems = $notifications->getCollection()->filter(function (DatabaseNotification $n) use ($filter) {
                $p = NotificationPresenter::present($n->data ?? []);
                return $p['category'] === $filter;
            });
            $notifications->setCollection($filteredItems);
        }

        return view('notifications.index', compact('notifications', 'filter'));
    }

    /**
     * Marca uma notificação individual como lida.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['status' => 'error', 'message' => 'Notificação não encontrada'], 404);
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Notificação marcada como lida.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca todas as notificações do utilizador como lidas.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Todas as notificações foram marcadas como lidas.',
            'unread_count' => 0,
        ]);
    }

    /**
     * Remove uma notificação do histórico.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['status' => 'error', 'message' => 'Notificação não encontrada'], 404);
        }

        $notification->delete();

        return response()->json([
            'ok' => true,
            'status' => 'success',
            'message' => 'Notificação removida com sucesso.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
