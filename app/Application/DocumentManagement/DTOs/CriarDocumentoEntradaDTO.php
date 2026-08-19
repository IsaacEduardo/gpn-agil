<?php

namespace App\Application\DocumentManagement\DTOs;

/**
 * Data Transfer Object (DTO) imutável para transferência de dados da camada HTTP para a camada de Aplicação.
 */
final class CriarDocumentoEntradaDTO
{
    public function __construct(
        public readonly string $numeroProtocolo,
        public readonly string $assunto,
        public readonly string $remetente,
        public readonly ?int $gabineteId = null,
        public readonly ?int $departamentoId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            numeroProtocolo: (string) ($data['numero_protocolo'] ?? $data['protocolo'] ?? 'PROT-' . time()),
            assunto: (string) ($data['assunto'] ?? ''),
            remetente: (string) ($data['origem'] ?? $data['remetente'] ?? ''),
            gabineteId: isset($data['gabinete_id']) ? (int) $data['gabinete_id'] : null,
            departamentoId: isset($data['departamento_id']) ? (int) $data['departamento_id'] : null
        );
    }
}
