<?php

namespace App\Services\Requisicao\Strategy;

use App\Models\Requisicao;

class PassagemStrategy implements RequisicaoStrategyInterface
{
    public function getRedirectRoute(): string
    {
        // Assumindo que exista ou seja o padrão
        return 'requisicoes.passagens.create.novo';
    }

    public function loadRelationships(Requisicao $requisicao): void
    {
        $requisicao->load('passagem');
    }

    public function deleteRelated(Requisicao $requisicao): void
    {
        $requisicao->passagem()->delete();
    }
}
