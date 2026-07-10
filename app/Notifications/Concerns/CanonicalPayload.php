<?php

namespace App\Notifications\Concerns;

/**
 * Contrato canónico de payload in-app/broadcast, partilhado por todas as notificações.
 *
 * Chaves canónicas consumidas pela UI:
 *   - title    (string)  Título curto e contextual.
 *   - body     (?string) Resumo descritivo (1 linha).
 *   - url       (?string) Deep-link para a ação.
 *   - priority (string)  'urgent' | 'high' | 'normal' | 'info'.
 *   - type     (?string) Tipo de evento legível por máquina (ex.: 'sla_critical').
 *   - icon     (?string) Classe de ícone (FontAwesome), opcional.
 *
 * Metadados de domínio (documento_id, numero, ...) vão em $extra e são preservados
 * sem sobrepor as chaves canónicas.
 */
trait CanonicalPayload
{
    protected function canonicalPayload(
        string $title,
        ?string $url = null,
        ?string $body = null,
        string $priority = 'normal',
        ?string $type = null,
        ?string $icon = null,
        array $extra = [],
    ): array {
        // $extra primeiro: as chaves canónicas têm precedência em caso de colisão.
        return array_merge($extra, [
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'priority' => $priority,
            'type' => $type,
            'icon' => $icon,
        ]);
    }
}
