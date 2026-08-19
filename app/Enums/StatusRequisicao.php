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

    public static function tryFromValue(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (mb_strtolower(trim($value))) {
            'aprovado', 'aprovada' => self::APROVADO,
            'assinado', 'assinada' => self::ASSINADO,
            'rejeitado', 'rejeitada' => self::REJEITADO,
            'concluido', 'concluida', 'finalizado' => self::FINALIZADO,
            'pendente' => self::PENDENTE,
            default => self::tryFrom($value),
        };
    }
}
