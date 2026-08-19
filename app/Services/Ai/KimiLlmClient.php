<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do LlmClient para o Kimi IA (Moonshot AI API).
 * A API da Moonshot/Kimi é 100% compatível com a especificação OpenAI Chat Completions.
 */
class KimiLlmClient implements LlmClient
{
    /**
     * @param  array<string,mixed>  $config  Bloco config('services.kimi').
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    public function chat(string $system, array $messages, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new LlmException('O assistente Kimi IA não está configurado. Defina KIMI_API_KEY no arquivo .env.');
        }

        $formattedMessages = [];
        if (! empty($system)) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        foreach (array_values($messages) as $m) {
            $formattedMessages[] = [
                'role' => $m['role'],
                'content' => $m['content'],
            ];
        }

        $payload = [
            'model' => $options['model'] ?? ($this->config['model'] ?? 'moonshot-v1-8k'),
            'messages' => $formattedMessages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024),
        ];

        try {
            $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.moonshot.ai/v1', '/');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->config['key'],
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 60))
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::error('Assistente Kimi IA: falha de rede', ['error' => $e->getMessage()]);
            throw new LlmException('Não foi possível contactar o serviço Kimi IA. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            Log::warning('Assistente Kimi IA: erro da API', ['status' => $response->status(), 'body' => $response->body()]);
            if ($response->status() === 429 || str_contains($response->body(), 'insufficient balance')) {
                throw new LlmException('A sua conta Kimi IA (Moonshot) necessita de recarga de saldo/crédito na plataforma. Verifique o seu plano em https://platform.moonshot.ai.');
            }
            throw new LlmException('O serviço Kimi IA devolveu um erro ('.$response->status().'). Verifique a validade da KIMI_API_KEY.');
        }

        $text = $response->json('choices.0.message.content') ?? '';
        $text = trim($text);

        return $text !== '' ? $text : 'Não foi possível gerar uma resposta com o Kimi IA.';
    }
}
