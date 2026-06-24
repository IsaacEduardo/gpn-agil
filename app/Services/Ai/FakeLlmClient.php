<?php

namespace App\Services\Ai;

/**
 * Cliente de IA de demonstração/teste. Não faz chamadas externas nem tem custo.
 * Útil para validar a UI/integração localmente (ASSISTENTE_DRIVER=fake) e como base em testes.
 * Devolve uma resposta determinística que ecoa a última pergunta do utilizador.
 */
class FakeLlmClient implements LlmClient
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function chat(string $system, array $messages, array $options = []): string
    {
        $ultima = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $ultima = trim((string) ($m['content'] ?? ''));
                break;
            }
        }

        return '[Modo de demonstração] Com base no conteúdo fornecido, esta é uma resposta de exemplo para: "'
            .$ultima.'". (Assistente em modo de teste — sem custos e sem chamadas externas.)';
    }
}
