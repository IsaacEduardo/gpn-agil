<?php

namespace App\Services\Ai;

/**
 * Abstração do provedor de IA. Isolar o provedor por trás desta interface permite
 * trocar a API Claude (nuvem) por um modelo on-premise (ex.: Ollama) por configuração,
 * sem reescrever a lógica do assistente.
 */
interface LlmClient
{
    /**
     * Indica se o cliente está configurado (ex.: chave de API presente).
     */
    public function isConfigured(): bool;

    /**
     * Gera uma resposta a partir de instruções de sistema + mensagens da conversa.
     *
     * @param  string  $system  Instruções do sistema (inclui o contexto dos documentos).
     * @param  array<int, array{role:string, content:string}>  $messages  Deve começar por 'user' e alternar.
     * @param  array<string,mixed>  $options  ex.: model, max_tokens, temperature.
     * @return string Texto da resposta.
     *
     * @throws \App\Services\Ai\LlmException
     */
    public function chat(string $system, array $messages, array $options = []): string;
}
