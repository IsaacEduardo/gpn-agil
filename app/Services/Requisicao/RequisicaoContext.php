<?php

namespace App\Services\Requisicao;

use App\Services\Requisicao\Strategy\OficinaStrategy;
use App\Services\Requisicao\Strategy\PassagemStrategy;
use App\Services\Requisicao\Strategy\ProdutoStrategy;
use App\Services\Requisicao\Strategy\RequisicaoStrategyInterface;
use App\Services\Requisicao\Strategy\ServicoStrategy;

class RequisicaoContext
{
    public static function getStrategy(string $tipo): RequisicaoStrategyInterface
    {
        return match ($tipo) {
            'produto' => new ProdutoStrategy,
            'oficina' => new OficinaStrategy,
            'servico' => new ServicoStrategy,
            'passagem' => new PassagemStrategy,
            default => new ProdutoStrategy, // Fallback
        };
    }
}
