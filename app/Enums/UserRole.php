<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CHEFE_DEPARTAMENTO = 'chefe-departamento';
    // Add other roles as discovered, for now these are the ones explicitly checked in code

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::CHEFE_DEPARTAMENTO => 'Chefe de Departamento',
        };
    }
}
