<?php

namespace App\Domain\RequisitionFleet\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável representando a Matrícula de Viatura Oficial.
 */
final class MatriculaViaturaValueObject
{
    private string $matricula;

    public function __construct(string $matricula)
    {
        $matricula = strtoupper(trim($matricula));

        if (empty($matricula)) {
            throw new InvalidArgumentException('A matrícula da viatura não pode ser vazia.');
        }

        $this->matricula = $matricula;
    }

    public function getMatricula(): string
    {
        return $this->matricula;
    }

    public function equals(MatriculaViaturaValueObject $other): bool
    {
        return $this->matricula === $other->getMatricula();
    }

    public function __toString(): string
    {
        return $this->matricula;
    }
}
