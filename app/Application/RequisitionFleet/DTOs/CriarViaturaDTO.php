<?php

namespace App\Application\RequisitionFleet\DTOs;

/**
 * Data Transfer Object (DTO) imutável para transferência de dados
 * da camada HTTP para a camada de Aplicação no contexto de Viaturas.
 */
final class CriarViaturaDTO
{
    public function __construct(
        public readonly string  $matricula,
        public readonly string  $marcaModelo,
        public readonly string  $statusOperacional = 'operacional',
        public readonly ?int    $departamentoId = null,
        public readonly ?string $codigoInterno = null,  // mapeia para 'identificacao' na BD
        public readonly ?int    $viaturaId = null
    ) {}

    /**
     * Constrói o DTO a partir dos dados validados do formulário.
     * Mapeia 'placa' → 'matricula' e 'marca'+'modelo' → 'marcaModelo'.
     */
    public static function fromArray(array $data): self
    {
        // O domínio usa 'marcaModelo'; o formulário envia 'marca' e 'modelo' separados.
        $marcaModelo = trim(
            ($data['marca'] ?? '') . ' ' . ($data['modelo'] ?? '')
        );
        if ($marcaModelo === ' ') {
            $marcaModelo = (string) ($data['marcaModelo'] ?? 'Não especificado');
        }

        // Mapeia status operacional: formulário usa valores PT; domínio usa lowercase sem acentos.
        $statusMap = [
            'Operacional'    => 'operacional',
            'Em manutenção'  => 'manutencao',
            'Inoperante'     => 'inoperante',
        ];
        $statusForm = (string) ($data['status_operacional'] ?? 'Operacional');
        $status = $statusMap[$statusForm] ?? strtolower($statusForm);

        return new self(
            matricula: strtoupper((string) ($data['placa'] ?? $data['matricula'] ?? '')),
            marcaModelo: $marcaModelo,
            statusOperacional: $status,
            departamentoId: isset($data['departamento_id']) ? (int) $data['departamento_id'] : null,
            codigoInterno: isset($data['identificacao']) ? (string) $data['identificacao'] : null,
        );
    }
}
