<?php

namespace App\Domain\DocumentManagement\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object imutável representando a validação criptográfica do Hash de Assinatura PKCS#12.
 */
final class HashAssinaturaValueObject
{
    private string $hash;

    public function __construct(string $hash)
    {
        $hash = trim($hash);

        if (empty($hash) || strlen($hash) < 32) {
            throw new InvalidArgumentException('Hash de assinatura inválido ou muito curto.');
        }

        $this->hash = strtolower($hash);
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function equals(HashAssinaturaValueObject $other): bool
    {
        return $this->hash === $other->getHash();
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
