<?php

namespace Tests\Unit\Services;

use App\Services\Ai\OpenAiLlmClient;
use App\Services\Ai\LlmException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiLlmClientTest extends TestCase
{
    public function test_is_not_configured_without_api_key()
    {
        $client = new OpenAiLlmClient(['key' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_throws_exception_when_chatting_without_api_key()
    {
        $this->expectException(LlmException::class);
        $client = new OpenAiLlmClient(['key' => '']);
        $client->chat('System prompt', [['role' => 'user', 'content' => 'Olá']]);
    }

    public function test_sends_chat_completion_request_to_openai_api()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Olá! Eu sou o assistente ChatGPT.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new OpenAiLlmClient([
            'key' => 'test-openai-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ]);

        $resposta = $client->chat('Instruções', [['role' => 'user', 'content' => 'Qual é o teu nome?']]);

        $this->assertEquals('Olá! Eu sou o assistente ChatGPT.', $resposta);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request['model'] === 'gpt-4o-mini';
        });
    }

    public function test_throws_friendly_exception_on_quota_exceeded()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota',
                    'type' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('saldo/crédito');

        $client = new OpenAiLlmClient([
            'key' => 'test-openai-key',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $client->chat('Instruções', [['role' => 'user', 'content' => 'Teste']]);
    }
}
