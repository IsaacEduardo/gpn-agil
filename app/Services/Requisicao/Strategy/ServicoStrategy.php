<?php

namespace App\Services\Requisicao\Strategy;

use App\Models\Requisicao;

class ServicoStrategy implements RequisicaoStrategyInterface
{
    public function getRedirectRoute(): string
    {
        return 'requisicoes.servicos.create.novo';
    }

    public function loadRelationships(Requisicao $requisicao): void
    {
        $requisicao->load('servicos');
    }

    public function deleteRelated(Requisicao $requisicao): void
    {
        $requisicao->servico()->delete();
    }
}
