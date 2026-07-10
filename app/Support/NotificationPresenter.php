<?php

namespace App\Support;

/**
 * Normaliza o payload de uma notificação para o contrato canónico consumido pela UI.
 *
 * Tolera chaves de payloads legados (titulo/mensagem/action_url/acao/tipo) para que
 * notificações já gravadas em base de dados continuem a renderizar corretamente.
 */
class NotificationPresenter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{title:string, body:?string, url:?string, priority:string, type:?string, icon:?string}
     */
    public static function present(array $data): array
    {
        return [
            'title' => $data['title'] ?? $data['titulo'] ?? $data['message'] ?? $data['mensagem'] ?? 'Notificação',
            'body' => $data['body'] ?? $data['message'] ?? $data['mensagem'] ?? null,
            'url' => $data['url'] ?? $data['action_url'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'type' => $data['type'] ?? $data['tipo'] ?? $data['acao'] ?? null,
            'icon' => $data['icon'] ?? null,
        ];
    }

    /**
     * Cor do indicador de prioridade usado no ponto da lista de notificações.
     */
    public static function priorityColor(string $priority): string
    {
        return match ($priority) {
            'urgent' => '#dc3545',
            'high' => '#fd7e14',
            'info' => '#6c757d',
            default => '#0d6efd',
        };
    }
}
