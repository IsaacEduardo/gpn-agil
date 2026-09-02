<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do LlmClient para a API OpenAI (ChatGPT - GPT-4o / GPT-4o-mini / etc).
 */
class OpenAiLlmClient implements LlmClient
{
    /**
     * @param  array<string,mixed>  $config  Bloco config('services.openai').
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    public function chat(string $system, array $messages, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new LlmException('O assistente OpenAI (ChatGPT) não está configurado. Defina OPENAI_API_KEY no arquivo .env.');
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
            'model' => $options['model'] ?? ($this->config['model'] ?? 'gpt-4o-mini'),
            'messages' => $formattedMessages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024),
        ];

        try {
            $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->config['key'],
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 60))
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::error('Assistente OpenAI (ChatGPT): falha de rede', ['error' => $e->getMessage()]);
            throw new LlmException('Não foi possível contactar o serviço OpenAI (ChatGPT). Tente novamente em instantes.');
        }

        if ($response->failed()) {
            Log::warning('Assistente OpenAI (ChatGPT): erro da API', ['status' => $response->status(), 'body' => $response->body()]);
            if ($response->status() === 429 || str_contains($response->body(), 'insufficient_quota')) {
                throw new LlmException('A sua conta OpenAI necessita de recarga de saldo/crédito ou atingiu o limite de taxa de requisições. Verifique o seu plano em https://platform.openai.com/usage.');
            }
            if ($response->status() === 401) {
                throw new LlmException('A OPENAI_API_KEY configurada é inválida ou expirou. Verifique as credenciais no arquivo .env.');
            }
            throw new LlmException('O serviço OpenAI (ChatGPT) devolveu um erro ('.$response->status().'). Verifique a validade da OPENAI_API_KEY.');
        }

        $text = $response->json('choices.0.message.content') ?? '';
        $text = trim($text);

        return $text !== '' ? $text : 'Não foi possível gerar uma resposta com o OpenAI ChatGPT.';
    }
}
