<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\DocumentManagement\Entities\DocumentoEntradaEntity;
use App\Domain\DocumentManagement\Repositories\DocumentoEntradaRepositoryInterface;
use App\Domain\DocumentManagement\ValueObjects\NumeroProtocoloValueObject;
use App\Domain\DocumentManagement\ValueObjects\HashAssinaturaValueObject;
use App\Models\DocumentoEntrada as EloquentDocumentoEntrada;

/**
 * Implementação de Repositório baseada em Eloquent ORM na Camada de Infraestrutura.
 * Converte Modelos Eloquent de/para Entidades puras de Domínio.
 */
class EloquentDocumentoEntradaRepository implements DocumentoEntradaRepositoryInterface
{
    public function findById(int $id): ?DocumentoEntradaEntity
    {
        $model = EloquentDocumentoEntrada::find($id);
        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByProtocolo(NumeroProtocoloValueObject $protocolo): ?DocumentoEntradaEntity
    {
        $model = EloquentDocumentoEntrada::where('numero_protocolo', $protocolo->getValue())->first();
        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function save(DocumentoEntradaEntity $entity): DocumentoEntradaEntity
    {
        $data = [
            // 'classificacao_ref_numero' é o campo da BD que mapeia ao protocolo de domínio.
            'classificacao_ref_numero' => $entity->getProtocolo()->getValue(),
            'assunto'                  => $entity->getAssunto(),
            // 'procedencia' é o campo real da tabela documentos_entradas (não 'origem').
            'procedencia'              => $entity->getRemetente(),
            'status'                   => $entity->getStatus(),
            'departamento_id'          => $entity->getDepartamentoId(),
        ];

        if ($entity->getHashAssinatura() !== null) {
            $data['assinatura_hash'] = $entity->getHashAssinatura()->getHash();
        }

        if ($entity->getId() !== null) {
            $model = EloquentDocumentoEntrada::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = EloquentDocumentoEntrada::create($data);
        }

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return (bool) EloquentDocumentoEntrada::destroy($id);
    }

    private function toEntity(EloquentDocumentoEntrada $model): DocumentoEntradaEntity
    {
        $hash = $model->assinatura_hash ? new HashAssinaturaValueObject($model->assinatura_hash) : null;

        // Mapeia 'classificacao_ref_numero' (BD) → NumeroProtocoloValueObject (Domínio).
        $protocoloStr = $model->classificacao_ref_numero
            ?? $model->numero_protocolo
            ?? ('DOC-' . $model->numero_sequencial . '/' . $model->ano_referencia);

        return new DocumentoEntradaEntity(
            $model->id,
            new NumeroProtocoloValueObject($protocoloStr),
            $model->assunto ?? '',
            // 'procedencia' é o campo real da tabela (mapeado para remetente no domínio).
            $model->procedencia ?? '',
            $model->status ?? 'pendente',
            null, // gabinete_id não existe na tabela; resolvido via departamento
            $model->departamento_id,
            $hash
        );
    }
}
