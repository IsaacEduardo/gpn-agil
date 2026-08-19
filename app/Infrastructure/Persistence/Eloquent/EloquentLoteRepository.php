<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\LandManagement\Entities\LoteEntity;
use App\Domain\LandManagement\Repositories\LoteRepositoryInterface;
use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;
use App\Models\Lote as EloquentLote;

/**
 * Implementação de Repositório Eloquent para Lotes Territoriais.
 */
class EloquentLoteRepository implements LoteRepositoryInterface
{
    public function findById(int $id): ?LoteEntity
    {
        $model = EloquentLote::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findByCodigo(CodigoLoteValueObject $codigo): ?LoteEntity
    {
        // Usa 'codigo_lote' (coluna real na BD), não 'codigo'.
        $model = EloquentLote::where('codigo_lote', $codigo->getCodigo())->first();
        return $model ? $this->toEntity($model) : null;
    }

    public function save(LoteEntity $entity): LoteEntity
    {
        $data = [
            // O campo real da tabela é 'codigo_lote', não 'codigo'.
            'codigo_lote'   => $entity->getCodigo()->getCodigo(),
            'area_m2'       => $entity->getAreaM2(),
            // 'localizacao' não é um campo directo; guardamos em 'bairro_distrito'.
            'bairro_distrito' => $entity->getLocalizacao(),
            // Domínio usa lowercase; BD usa uppercase (DISPONIVEL, ATRIBUIDO, etc.).
            'status'        => strtoupper($entity->getStatus()),
            'requerente_id' => $entity->getRequerenteId(),
        ];

        if ($entity->getId() !== null) {
            $model = EloquentLote::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = EloquentLote::create($data);
        }

        return $this->toEntity($model);
    }

    public function delete(int $id): bool
    {
        return (bool) EloquentLote::destroy($id);
    }

    private function toEntity(EloquentLote $model): LoteEntity
    {
        // Combina campos geo para formar a localização conceptual do domínio.
        $localizacao = trim(
            ($model->bairro_distrito ?? '') . ', ' . ($model->municipio ?? '')
        );
        if ($localizacao === ', ' || $localizacao === '') {
            $localizacao = 'Localização não especificada';
        }

        // BD guarda uppercase; Domínio usa lowercase.
        $status = strtolower($model->status ?? 'disponivel');

        return new LoteEntity(
            $model->id,
            new CodigoLoteValueObject($model->codigo_lote ?? ('LOTE-' . $model->id)),
            (float) ($model->area_m2 ?? 0),
            $localizacao,
            $status,
            $model->requerente_id
        );
    }
}
