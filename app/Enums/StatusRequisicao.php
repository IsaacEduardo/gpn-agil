<?php

namespace App\Enums;

enum StatusRequisicao: string
{
    case PENDENTE = 'pendente';
    case ASSINADO = 'assinada';
    case APROVADO = 'aprovada';
    case REJEITADO = 'rejeitada';
    case FINALIZADO = 'concluida';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::ASSINADO => 'Assinada',
            self::APROVADO => 'Aprovada',
            self::REJEITADO => 'Rejeitada',
            self::FINALIZADO => 'Concluída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDENTE => 'warning',
            self::ASSINADO => 'primary',
            self::APROVADO => 'success',
            self::REJEITADO => 'danger',
            self::FINALIZADO => 'info',
        };
    }
}
