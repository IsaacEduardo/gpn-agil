<?php

namespace App\Chatbot\Contracts;

/**
 * Provedor de embeddings (vetores). Isolado por interface para permitir trocar
 * entre on-premise (Ollama), OpenAI, ou um fake determinístico (testes).
 */
interface EmbeddingClient
{
    public function isConfigured(): bool;

    /**
     * Gera o embedding de um texto.
     *
     * @return array<int, float>
     *
     * @throws \App\Chatbot\Exceptions\EmbeddingException
     */
    public function embed(string $text): array;

    /**
     * Gera embeddings para vários textos.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws \App\Chatbot\Exceptions\EmbeddingException
     */
    public function embedBatch(array $texts): array;

    public function model(): string;

    public function dimensions(): int;
}
