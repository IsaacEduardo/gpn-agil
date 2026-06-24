<?php

namespace App\Chatbot\Embeddings;

use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Embeddings via API OpenAI (opcional). Atenção: envia trechos dos documentos
 * para a OpenAI (saída de dados) — usar apenas se a política de dados permitir.
 */
class OpenAiEmbeddingClient implements EmbeddingClient
{
    /**
     * @param  array<string,mixed>  $config  config('chatbot.embeddings')
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['openai_key']);
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        if (! $this->isConfigured()) {
            throw new EmbeddingException('Embeddings OpenAI não configurados (OPENAI_API_KEY ausente).');
        }

        $base = rtrim((string) ($this->config['openai_base_url'] ?? 'https://api.openai.com'), '/');

        try {
            $response = Http::withToken($this->config['openai_key'])
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->post($base.'/v1/embeddings', [
                    'model' => $this->config['model'],
                    'input' => array_values($texts),
                ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot embeddings: falha de rede (OpenAI)', ['error' => $e->getMessage()]);
            throw new EmbeddingException('Não foi possível contactar o serviço de embeddings (OpenAI).');
        }

        if ($response->failed()) {
            throw new EmbeddingException('O serviço de embeddings (OpenAI) devolveu um erro ('.$response->status().').');
        }

        return array_map(
            fn ($item) => array_map('floatval', $item['embedding']),
            $response->json('data') ?? []
        );
    }

    public function model(): string
    {
        return (string) $this->config['model'];
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 1536);
    }
}
