<?php

namespace App\Notifications;

/**
 * Agrupa os 'type' canónicos das notificações em categorias geríveis pelo
 * utilizador nas preferências de subscrição.
 */
class NotificationCategory
{
    /**
     * Categorias e respetivos rótulos (para a UI de preferências).
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'tarefas' => 'Tarefas',
        'documentos' => 'Documentos (recebidos, enviados e encaminhamentos)',
        'workflow' => 'Tramitação (análise, aprovação e devolução)',
        'sla' => 'Prazos e retenção',
        'requisicoes' => 'Requisições',
        'colaboracao' => 'Colaboração',
        'outros' => 'Outros',
    ];

    /**
     * Canais geríveis pelo utilizador. O canal 'database' (in-app) é sempre
     * mantido, para garantir o registo e a rastreabilidade.
     *
     * @var array<string, string>
     */
    public const MANAGEABLE_CHANNELS = [
        'mail' => 'E-mail',
        'broadcast' => 'Tempo real',
    ];

    /**
     * Mapa de type canónico -> categoria.
     *
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'tarefa_delegada' => 'tarefas',
        'tarefa_concluida' => 'tarefas',
        'tarefa_cancelada' => 'tarefas',

        'documento_recebido' => 'documentos',
        'documento_enviado' => 'documentos',
        'documento_encaminhado_interno' => 'documentos',
        'documento_encaminhado_externo' => 'documentos',

        'documento_enviado_analise' => 'workflow',
        'documento_aprovado' => 'workflow',
        'documento_devolvido' => 'workflow',

        'sla_warning' => 'sla',
        'sla_critical' => 'sla',
        'retencao' => 'sla',

        'requisicao_assinada' => 'requisicoes',

        'convite_colaboracao' => 'colaboracao',
    ];

    public static function forType(?string $type): string
    {
        if ($type === null) {
            return 'outros';
        }

        return self::TYPE_MAP[$type] ?? 'outros';
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::CATEGORIES);
    }

    public static function isManageableChannel(string $channel): bool
    {
        return array_key_exists($channel, self::MANAGEABLE_CHANNELS);
    }
}
