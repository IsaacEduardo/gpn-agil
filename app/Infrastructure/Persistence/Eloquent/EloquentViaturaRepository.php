<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\RequisitionFleet\Entities\ViaturaEntity;
use App\Domain\RequisitionFleet\Repositories\ViaturaRepositoryInterface;
use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;
use App\Models\Viatura as EloquentViatura;

/**
 * Implementação de Repositório Eloquent para Viaturas da Frota.
 */
class EloquentViaturaRepository implements ViaturaRepositoryInterface
{
    public function findById(int $id): ?ViaturaEntity
    {
        $model = EloquentViatura::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findByMatricula(MatriculaViaturaValueObject $matricula): ?ViaturaEntity
    {
        // Usa 'placa' (coluna real na BD), não 'matricula'.
        $model = EloquentViatura::where('placa', $matricula->getMatricula())->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function save(ViaturaEntity $entity): ViaturaEntity
    {
        $data = [
            'placa'              => $entity->getMatricula()->getMatricula(),
            'modelo'             => $entity->getMarcaModelo(),
            // Mapeia status do domínio (lowercase) para o formato da BD do formulário.
            'status_operacional' => $this->mapStatusToDb($entity->getStatusOperacional()),
        ];

        if ($entity->getId() === null) {
            // Para novos registos, 'identificacao' é NOT NULL na BD.
            // O Controller normaliza o valor antes de chamar o Handler; se não
            // estiver disponível, usamos a matrícula como fallback seguro.
            $data['identificacao'] = $entity->getCodigoInterno()
                ?? ('V-' . strtolower($entity->getMatricula()->getMatricula()));
        }

        if ($entity->getId() !== null) {
            $model = EloquentViatura::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = EloquentViatura::create($data);
        }

        return $this->toEntity($model);
    }

    /**
     * Mapeia o status de domínio (lowercase) para o valor aceite pelo formulário/BD.
     */
    private function mapStatusToDb(string $domainStatus): string
    {
        return match ($domainStatus) {
            'manutencao' => 'Em manutenção',
            'inoperante' => 'Inoperante',
            default      => 'Operacional',
        };
    }

    public function delete(int $id): bool
    {
        return (bool) EloquentViatura::destroy($id);
    }

    private function toEntity(EloquentViatura $model): ViaturaEntity
    {
        // Mapeia status da BD (PT com acentos) para lowercase do domínio.
        $statusMap = [
            'Operacional'   => 'operacional',
            'Em manutenção' => 'manutencao',
            'Inoperante'    => 'inoperante',
        ];
        $status = $statusMap[$model->status_operacional ?? 'Operacional'] ?? 'operacional';

        // 'placa' é o campo de matrícula real na tabela viaturas.
        $matriculaStr = $model->placa ?? $model->matricula ?? 'LD-00-00-AA';

        $marcaModelo = trim(($model->marca ?? '') . ' ' . ($model->modelo ?? ''));
        if ($marcaModelo === ' ' || $marcaModelo === '') {
            $marcaModelo = 'Modelo Não Especificado';
        }

        return new ViaturaEntity(
            $model->id,
            new MatriculaViaturaValueObject($matriculaStr),
            $marcaModelo,
            $status,
            $model->departamento_id ?? null,  // Correcção: LAC-07 — passar departamento_id
            $model->identificacao ?? null
        );
    }
}
