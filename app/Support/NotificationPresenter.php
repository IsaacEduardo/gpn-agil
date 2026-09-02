<?php

namespace App\Support;

/**
 * Normaliza o payload de uma notificação para o contrato canónico consumido pela UI.
 *
 * Tolera chaves de payloads legados (titulo/mensagem/action_url/acao/tipo) para que
 * notificações já gravadas em base de dados continuem a renderizar perfeitamente.
 */
class NotificationPresenter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     title: string,
     *     body: ?string,
     *     url: ?string,
     *     priority: string,
     *     type: ?string,
     *     category: string,
     *     icon: string,
     *     icon_bg: string,
     *     priority_color: string
     * }
     */
    public static function present(array $data): array
    {
        $type = $data['type'] ?? $data['tipo'] ?? $data['acao'] ?? null;
        $title = $data['title'] ?? $data['titulo'] ?? $data['message'] ?? $data['mensagem'] ?? 'Notificação do Sistema';
        $body = $data['body'] ?? $data['message'] ?? $data['mensagem'] ?? null;
        $url = $data['url'] ?? $data['action_url'] ?? null;
        $priority = $data['priority'] ?? 'normal';

        $category = self::resolveCategory($type, $data['category'] ?? null, $title);
        $icon = $data['icon'] ?? self::resolveIcon($type, $category, $title);
        $iconBg = self::resolveIconBg($type, $category, $priority);
        $priorityColor = self::priorityColor($priority);

        return [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'priority' => $priority,
            'type' => $type,
            'category' => $category,
            'icon' => $icon,
            'icon_bg' => $iconBg,
            'priority_color' => $priorityColor,
        ];
    }

    /**
     * Resolve a categoria canónica para abas de filtro na Central de Notificações.
     * Valores retornados: 'workflow' (Tarefas, Despachos, Vistos, Encaminhamentos) ou 'system' (OCR, SLA, Exportações, Segurança).
     */
    public static function resolveCategory(?string $type, ?string $explicitCategory, string $title): string
    {
        if ($explicitCategory) {
            return in_array($explicitCategory, ['workflow', 'tarefas', 'despachos'], true) ? 'workflow' : 'system';
        }

        $workflowTypes = [
            'tarefa_delegada', 'tarefa_concluida', 'tarefa_cancelada',
            'documento_recebido', 'documento_enviado', 'documento_encaminhado_interno', 'documento_encaminhado_externo',
            'documento_enviado_analise', 'documento_aprovado', 'documento_devolvido',
            'requisicao_assinada', 'convite_colaboracao', 'despacho', 'visto', 'homologacao'
        ];

        if ($type && in_array(strtolower($type), $workflowTypes, true)) {
            return 'workflow';
        }

        $titleLower = strtolower($title);
        if (
            str_contains($titleLower, 'tarefa') ||
            str_contains($titleLower, 'despacho') ||
            str_contains($titleLower, 'visto') ||
            str_contains($titleLower, 'encaminh') ||
            str_contains($titleLower, 'documento') ||
            str_contains($titleLower, 'homolog')
        ) {
            return 'workflow';
        }

        return 'system';
    }

    /**
     * Resolve o ícone FontAwesome representativo.
     */
    public static function resolveIcon(?string $type, string $category, string $title): string
    {
        $type = strtolower($type ?? '');
        $titleLower = strtolower($title);

        if (str_contains($type, 'tarefa') || str_contains($titleLower, 'tarefa')) {
            return 'fas fa-tasks';
        }

        if (str_contains($type, 'despacho') || str_contains($titleLower, 'despacho') || str_contains($titleLower, 'visto')) {
            return 'fas fa-file-signature';
        }

        if (str_contains($type, 'encaminh') || str_contains($titleLower, 'encaminh') || str_contains($type, 'documento_recebido')) {
            return 'fas fa-folder-open';
        }

        if (str_contains($type, 'sla') || str_contains($titleLower, 'prazo') || str_contains($titleLower, 'sla')) {
            return 'fas fa-clock';
        }

        if (str_contains($type, 'ocr') || str_contains($titleLower, 'ocr')) {
            return 'fas fa-eye';
        }

        if (str_contains($type, 'colabora') || str_contains($titleLower, 'colabora')) {
            return 'fas fa-user-friends';
        }

        if (str_contains($type, 'requisicao') || str_contains($titleLower, 'requisi')) {
            return 'fas fa-clipboard-check';
        }

        return $category === 'workflow' ? 'fas fa-file-alt' : 'fas fa-bell';
    }

    /**
     * Resolve as classes de cores e fundo suave para o ícone.
     */
    public static function resolveIconBg(?string $type, string $category, string $priority): string
    {
        if ($priority === 'urgent') {
            return 'bg-danger-subtle text-danger border border-danger-subtle';
        }
        if ($priority === 'high') {
            return 'bg-warning-subtle text-warning border border-warning-subtle';
        }

        $type = strtolower($type ?? '');

        if (str_contains($type, 'tarefa')) {
            return 'bg-primary-subtle text-primary border border-primary-subtle';
        }
        if (str_contains($type, 'despacho') || str_contains($type, 'aprovado')) {
            return 'bg-success-subtle text-success border border-success-subtle';
        }
        if (str_contains($type, 'encaminh') || str_contains($type, 'recebido')) {
            return 'bg-info-subtle text-info border border-info-subtle';
        }
        if (str_contains($type, 'sla')) {
            return 'bg-warning-subtle text-warning border border-warning-subtle';
        }
        if (str_contains($type, 'colabora')) {
            return 'bg-purple-subtle text-purple border border-purple-subtle';
        }

        return 'bg-light text-secondary border';
    }

    /**
     * Cor do indicador de prioridade usado no ponto da lista de notificações.
     */
    public static function priorityColor(string $priority): string
    {
        return match ($priority) {
            'urgent' => '#dc2626',
            'high' => '#d97706',
            'info' => '#2563eb',
            default => '#059669',
        };
    }
}
