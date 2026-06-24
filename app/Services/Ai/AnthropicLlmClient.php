<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do LlmClient usando a Messages API da Anthropic (Claude).
 * Usa o Http facade (sem dependência adicional) e prompt caching do contexto.
 */
class AnthropicLlmClient implements LlmClient
{
    /**
     * @param  array<string,mixed>  $config  Bloco config('services.anthropic').
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    public function chat(string $system, array $messages, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new LlmException('O assistente de IA ainda não está configurado (chave de API ausente).');
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['model'],
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 1024),
            'temperature' => $options['temperature'] ?? 0.1,
            // Cabeça do "system" com cache_control: barateia conversas multi-turn sobre o mesmo contexto.
            'system' => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => array_map(fn ($m) => [
                'role' => $m['role'],
                'content' => $m['content'],
            ], array_values($messages)),
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->config['key'],
                'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 60))
                ->post(rtrim($this->config['base_url'] ?? 'https://api.anthropic.com', '/').'/v1/messages', $payload);
        } catch (\Throwable $e) {
            Log::error('Assistente IA: falha de rede', ['error' => $e->getMessage()]);
            throw new LlmException('Não foi possível contactar o serviço de IA. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            Log::warning('Assistente IA: erro da API', ['status' => $response->status(), 'body' => $response->body()]);
            throw new LlmException('O serviço de IA devolveu um erro ('.$response->status().'). Tente novamente.');
        }

        // Messages API: content é um array de blocos { type: 'text', text: '...' }.
        $text = '';
        foreach (($response->json('content') ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        $text = trim($text);

        return $text !== '' ? $text : 'Não foi possível gerar uma resposta para esta pergunta.';
    }
}
