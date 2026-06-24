<?php

namespace App\Enums;

enum DocumentoStatus: string
{
    case RASCUNHO = 'rascunho';
    case EM_ANALISE = 'em_analise';
    case APROVADO = 'aprovado';
    case ASSINADO = 'assinado';
    case ARQUIVADO = 'arquivado';

    // Legacy/Existing statuses map to new ones or keep if needed for backward compatibility
    // but better to migrate data. For now, I'll keep them to avoid breaking existing data immediately
    case REGISTRADO = 'registrado';
    case ENCAMINHADO = 'encaminhado';
    case RECEBIDO = 'recebido';
    case ENCAMINHADO_EXTERNO = 'encaminhado_externo';
    case FINALIZADO = 'finalizado'; // Legacy support

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::EM_ANALISE => 'Em Análise',
            self::APROVADO => 'Aprovado',
            self::ASSINADO => 'Assinado',
            self::ARQUIVADO => 'Arquivado',
            self::REGISTRADO => 'Registrado',
            self::ENCAMINHADO => 'Encaminhado',
            self::RECEBIDO => 'Recebido',
            self::ENCAMINHADO_EXTERNO => 'Encaminhado Externo',
            self::FINALIZADO => 'Finalizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RASCUNHO => 'secondary',
            self::EM_ANALISE => 'warning',
            self::APROVADO => 'primary',
            self::ASSINADO => 'success',
            self::ARQUIVADO => 'dark',
            self::REGISTRADO => 'info',
            self::ENCAMINHADO => 'warning',
            self::RECEBIDO => 'success',
            self::ENCAMINHADO_EXTERNO => 'info',
            self::FINALIZADO => 'success',
        };
    }
}
