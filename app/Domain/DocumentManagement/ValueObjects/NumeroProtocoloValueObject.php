<?php

namespace App\Domain\DocumentManagement\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável representando o Número de Protocolo do Documento.
 * Garantia de invariante de formato sem dependência de framework.
 */
final class NumeroProtocoloValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if (empty($value)) {
            throw new InvalidArgumentException('Número de protocolo não pode ser vazio.');
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(NumeroProtocoloValueObject $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
