<?php

namespace App\Enums;

enum StatusRequisicao: string
{
    case PENDENTE = 'pendente';
    case APROVADO = 'aprovada';
    case REJEITADO = 'rejeitada';
    case FINALIZADO = 'concluida';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::APROVADO => 'Aprovada',
            self::REJEITADO => 'Rejeitada',
            self::FINALIZADO => 'Concluída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDENTE => 'warning',
            self::APROVADO => 'success',
            self::REJEITADO => 'danger',
            self::FINALIZADO => 'info',
        };
    }
}
