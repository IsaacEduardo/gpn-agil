<?php

namespace App\Domain\LandManagement\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável representando o Código de Identificação do Lote Territorial.
 */
final class CodigoLoteValueObject
{
    private string $codigo;

    public function __construct(string $codigo)
    {
        $codigo = strtoupper(trim($codigo));

        if (empty($codigo)) {
            throw new InvalidArgumentException('O código do lote é obrigatório.');
        }

        $this->codigo = $codigo;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function equals(CodigoLoteValueObject $other): bool
    {
        return $this->codigo === $other->getCodigo();
    }

    public function __toString(): string
    {
        return $this->codigo;
    }
}
