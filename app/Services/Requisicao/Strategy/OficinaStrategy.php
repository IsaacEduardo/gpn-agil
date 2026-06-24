<?php

namespace App\Services\Requisicao\Strategy;

use App\Models\Requisicao;

class OficinaStrategy implements RequisicaoStrategyInterface
{
    public function getRedirectRoute(): string
    {
        return 'requisicoes.oficinas.create.novo';
    }

    public function loadRelationships(Requisicao $requisicao): void
    {
        $requisicao->load('oficina', 'oficina.viatura');
    }

    public function deleteRelated(Requisicao $requisicao): void
    {
        $requisicao->oficina()->delete();
    }
}
