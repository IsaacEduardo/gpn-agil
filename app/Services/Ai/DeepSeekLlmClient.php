<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do LlmClient para a API DeepSeek (deepseek-chat / deepseek-reasoner).
 * A API do DeepSeek é 100% compatível com a especificação OpenAI Chat Completions e Function Calling / Tools.
 */
class DeepSeekLlmClient implements LlmClient
{
    /**
     * @param  array<string,mixed>  $config  Bloco config('services.deepseek').
     */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    /**
     * Chamada simples de chat (retorna texto).
     */
    public function chat(string $system, array $messages, array $options = []): string
    {
        $res = $this->chatWithTools($system, $messages, [], $options);

        return $res['content'];
    }

    /**
     * Chamada avançada de chat com suporte a Tools / Function Calling.
     *
     * @param  string  $system  Instruções do sistema
     * @param  array<int, array<string,mixed>>  $messages  Mensagens (podem incluir role: user, assistant, tool)
     * @param  array<int, array<string,mixed>>  $tools  Definições de ferramentas (formato OpenAI Tools)
     * @param  array<string,mixed>  $options  Opções adicionais (model, max_tokens, temperature, etc)
     * @return array{content:string, tool_calls:array<int,array<string,mixed>>, finish_reason:?string, raw_message:array<string,mixed>}
     */
    public function chatWithTools(string $system, array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new LlmException('O assistente DeepSeek não está configurado. Defina DEEPSEEK_API_KEY no arquivo .env.');
        }

        $formattedMessages = [];
        if (! empty($system)) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        foreach (array_values($messages) as $m) {
            $entry = [
                'role' => $m['role'],
                'content' => $m['content'] ?? null,
            ];

            if (! empty($m['name'])) {
                $entry['name'] = $m['name'];
            }
            if (! empty($m['tool_call_id'])) {
                $entry['tool_call_id'] = $m['tool_call_id'];
            }
            if (! empty($m['tool_calls'])) {
                $entry['tool_calls'] = $m['tool_calls'];
            }

            $formattedMessages[] = $entry;
        }

        $payload = [
            'model' => $options['model'] ?? ($this->config['model'] ?? 'deepseek-chat'),
            'messages' => $formattedMessages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 2048),
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_values($tools);
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        try {
            $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.deepseek.com', '/');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->config['key'],
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) ($this->config['timeout'] ?? 60))
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::error('Assistente DeepSeek: falha de rede', ['error' => $e->getMessage()]);
            throw new LlmException('Não foi possível contactar o serviço DeepSeek. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            Log::warning('Assistente DeepSeek: erro da API', ['status' => $response->status(), 'body' => $response->body()]);
            if ($response->status() === 402 || str_contains($response->body(), 'Insufficient Balance') || str_contains($response->body(), 'insufficient_quota')) {
                throw new LlmException('A sua conta DeepSeek necessita de recarga de saldo/crédito na plataforma. Verifique em https://platform.deepseek.com/top_up.');
            }
            if ($response->status() === 401) {
                throw new LlmException('A DEEPSEEK_API_KEY configurada é inválida ou expirou. Verifique as credenciais no arquivo .env.');
            }
            if ($response->status() === 429) {
                throw new LlmException('O limite de requisições do DeepSeek foi atingido. Tente novamente em instantes.');
            }
            throw new LlmException('O serviço DeepSeek devolveu um erro ('.$response->status().'). Verifique a validade da DEEPSEEK_API_KEY.');
        }

        $choice = $response->json('choices.0') ?? [];
        $rawMessage = $choice['message'] ?? [];
        $text = trim((string) ($rawMessage['content'] ?? ''));
        $toolCalls = $rawMessage['tool_calls'] ?? [];
        $finishReason = $choice['finish_reason'] ?? null;

        return [
            'content' => $text !== '' ? $text : ($toolCalls ? '' : 'Não foi possível gerar uma resposta com o DeepSeek.'),
            'tool_calls' => $toolCalls,
            'finish_reason' => $finishReason,
            'raw_message' => $rawMessage,
        ];
    }
}
