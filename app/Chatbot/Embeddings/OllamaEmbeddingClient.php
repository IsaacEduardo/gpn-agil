<?php

namespace App\Chatbot\Embeddings;

use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Embeddings on-premise via Ollama (https://ollama.com).
 * Nenhum dado sai da infraestrutura. Endpoint: POST {base}/api/embeddings.
 */
class OllamaEmbeddingClient implements EmbeddingClient
{
    /**
     * @param  array<string,mixed>  $config  config('chatbot.embeddings')
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['ollama_base_url']) && ! empty($this->config['model']);
    }

    public function embed(string $text): array
    {
        $base = rtrim((string) $this->config['ollama_base_url'], '/');

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 30))
                ->post($base.'/api/embeddings', [
                    'model' => $this->config['model'],
                    'prompt' => $text,
                ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot embeddings: falha de rede (Ollama)', ['error' => $e->getMessage()]);
            throw new EmbeddingException('Não foi possível contactar o serviço de embeddings (Ollama).');
        }

        if ($response->failed()) {
            Log::warning('Chatbot embeddings: erro Ollama', ['status' => $response->status(), 'body' => $response->body()]);
            throw new EmbeddingException('O serviço de embeddings devolveu um erro ('.$response->status().').');
        }

        $vector = $response->json('embedding');
        if (! is_array($vector) || $vector === []) {
            throw new EmbeddingException('Resposta de embedding inválida do Ollama.');
        }

        return array_map('floatval', $vector);
    }

    public function embedBatch(array $texts): array
    {
        // Ollama processa um prompt por chamada; iteramos mantendo a ordem.
        return array_map(fn ($t) => $this->embed((string) $t), array_values($texts));
    }

    public function model(): string
    {
        return (string) $this->config['model'];
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 768);
    }
}
