<?php

namespace App\Enums;

enum TipoRelacaoDocumento: string
{
    case RESPOSTA = 'RESPOSTA';
    case INSTRUCAO_TECNICA = 'INSTRUCAO_TECNICA';
    case COMPLEMENTAR = 'COMPLEMENTAR';
    case APENSO_ANEXO = 'APENSO_ANEXO';

    public function label(): string
    {
        return match ($this) {
            self::RESPOSTA => 'Resposta Formal',
            self::INSTRUCAO_TECNICA => 'Instrução / Parecer Técnico',
            self::COMPLEMENTAR => 'Documento Complementar',
            self::APENSO_ANEXO => 'Apenso / Anexo',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RESPOSTA => 'bg-info-subtle text-info-emphasis border border-info-subtle',
            self::INSTRUCAO_TECNICA => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            self::COMPLEMENTAR => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::APENSO_ANEXO => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RESPOSTA => 'fas fa-reply',
            self::INSTRUCAO_TECNICA => 'fas fa-file-invoice',
            self::COMPLEMENTAR => 'fas fa-layer-group',
            self::APENSO_ANEXO => 'fas fa-paperclip',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RESPOSTA => 'O documento é a resposta formal / ofício devolutivo.',
            self::INSTRUCAO_TECNICA => 'Parecer, cota ou informação técnica que subsidia a tomada de decisão.',
            self::COMPLEMENTAR => 'Documento correlato, histórico anterior ou subsídio informativo.',
            self::APENSO_ANEXO => 'Processo ou documento apenso para tramitação e arquivamento conjuntos.',
        };
    }
}
