<?php

namespace App\Services\Requisicao\Strategy;

use App\Models\Requisicao;

class ProdutoStrategy implements RequisicaoStrategyInterface
{
    public function getRedirectRoute(): string
    {
        return 'requisicoes.produtos.create.novo';
    }

    public function loadRelationships(Requisicao $requisicao): void
    {
        $requisicao->load('produtos');
    }

    public function deleteRelated(Requisicao $requisicao): void
    {
        $requisicao->produtos()->delete();
    }
}
