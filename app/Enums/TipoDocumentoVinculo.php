<?php

namespace App\Enums;

enum TipoDocumentoVinculo: string
{
    case EXTERNO = 'EXTERNO';
    case INTERNO = 'INTERNO';

    public function label(): string
    {
        return match ($this) {
            self::EXTERNO => 'Documento Externo (Entrada)',
            self::INTERNO => 'Documento Interno',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::EXTERNO => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::INTERNO => 'bg-primary-subtle text-primary border border-primary-subtle',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EXTERNO => 'fas fa-inbox',
            self::INTERNO => 'fas fa-file-signature',
        };
    }
}
