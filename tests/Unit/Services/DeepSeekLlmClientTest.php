<?php

namespace Tests\Unit\Services;

use App\Services\Ai\DeepSeekLlmClient;
use App\Services\Ai\LlmException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekLlmClientTest extends TestCase
{
    public function test_is_not_configured_without_api_key()
    {
        $client = new DeepSeekLlmClient(['key' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_throws_exception_when_chatting_without_api_key()
    {
        $this->expectException(LlmException::class);
        $client = new DeepSeekLlmClient(['key' => '']);
        $client->chat('System prompt', [['role' => 'user', 'content' => 'Olá']]);
    }

    public function test_sends_chat_completion_request_to_deepseek_api()
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Olá! Eu sou o assistente DeepSeek.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new DeepSeekLlmClient([
            'key' => 'test-deepseek-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-chat',
        ]);

        $resposta = $client->chat('Instruções', [['role' => 'user', 'content' => 'Qual é o teu modelo?']]);

        $this->assertEquals('Olá! Eu sou o assistente DeepSeek.', $resposta);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-deepseek-key')
                && $request['model'] === 'deepseek-chat';
        });
    }

    public function test_throws_friendly_exception_on_insufficient_balance()
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Insufficient Balance',
                    'type' => 'invalid_request_error',
                ],
            ], 402),
        ]);

        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('saldo/crédito');

        $client = new DeepSeekLlmClient([
            'key' => 'test-deepseek-key',
            'base_url' => 'https://api.deepseek.com',
        ]);

        $client->chat('Instruções', [['role' => 'user', 'content' => 'Teste']]);
    }
}
