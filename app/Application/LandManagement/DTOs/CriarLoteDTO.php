<?php

namespace App\Application\LandManagement\DTOs;

/**
 * Data Transfer Object (DTO) imutável para transferência de dados
 * da camada HTTP para a camada de Aplicação no contexto de Lotes.
 */
final class CriarLoteDTO
{
    public function __construct(
        public readonly string $codigo,
        public readonly float  $areaM2,
        public readonly string $localizacao,
        public readonly string $status = 'disponivel',
        public readonly ?int   $requerenteId = null
    ) {}

    /**
     * Constrói o DTO a partir dos dados validados do formulário.
     * Mapeia os nomes dos campos do formulário para os conceitos de domínio.
     */
    public static function fromArray(array $data): self
    {
        // 'codigo_lote' é o campo do formulário; 'codigo' é o conceito de domínio.
        $codigo = (string) ($data['codigo_lote'] ?? $data['codigo'] ?? '');

        // 'bairro_distrito' + 'municipio' formam a localização contextual.
        $localizacao = trim(
            ($data['bairro_distrito'] ?? '') . ', ' . ($data['municipio'] ?? '')
        );
        if ($localizacao === ', ') {
            $localizacao = (string) ($data['localizacao'] ?? '');
        }

        // O domínio usa lowercase; o formulário envia uppercase (DISPONIVEL → disponivel).
        $status = strtolower((string) ($data['status'] ?? 'disponivel'));

        return new self(
            codigo: $codigo,
            areaM2: (float) ($data['area_m2'] ?? 0),
            localizacao: $localizacao,
            status: $status,
            requerenteId: isset($data['requerente_id']) ? (int) $data['requerente_id'] : null,
        );
    }
}
