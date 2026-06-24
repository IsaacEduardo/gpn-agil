<?php

namespace App\Services\Requisicao\Strategy;

use App\Models\Requisicao;

interface RequisicaoStrategyInterface
{
    /**
     * Retorna a rota de redirecionamento após a criação
     */
    public function getRedirectRoute(): string;

    /**
     * Carrega os relacionamentos necessários para visualização
     */
    public function loadRelationships(Requisicao $requisicao): void;

    /**
     * Exclui os dados relacionados antes de excluir a requisição
     */
    public function deleteRelated(Requisicao $requisicao): void;
}
