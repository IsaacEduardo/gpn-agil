<?php

namespace App\Enums;

/**
 * Níveis de permissão de um colaborador numa sessão de edição de Documento Interno.
 * Ordenados por privilégio crescente (ver rank()).
 */
enum NivelColaboracao: string
{
    case VISUALIZAR = 'visualizar';
    case COMENTAR = 'comentar';
    case EDITAR = 'editar';
    case ADMINISTRAR = 'administrar';

    public function rank(): int
    {
        return match ($this) {
            self::VISUALIZAR => 1,
            self::COMENTAR => 2,
            self::EDITAR => 3,
            self::ADMINISTRAR => 4,
        };
    }

    public function atLeast(self $outro): bool
    {
        return $this->rank() >= $outro->rank();
    }

    public function podeComentar(): bool
    {
        return $this->atLeast(self::COMENTAR);
    }

    public function podeEditar(): bool
    {
        return $this->atLeast(self::EDITAR);
    }

    public function podeAdministrar(): bool
    {
        return $this === self::ADMINISTRAR;
    }

    public function label(): string
    {
        return match ($this) {
            self::VISUALIZAR => 'Visualizar',
            self::COMENTAR => 'Comentar',
            self::EDITAR => 'Editar',
            self::ADMINISTRAR => 'Administrar',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $n) => $n->value, self::cases());
    }
}
