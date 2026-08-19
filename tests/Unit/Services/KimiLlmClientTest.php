<?php

namespace Tests\Unit\Services;

use App\Services\Ai\KimiLlmClient;
use App\Services\Ai\LlmException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KimiLlmClientTest extends TestCase
{
    public function test_is_not_configured_without_api_key()
    {
        $client = new KimiLlmClient(['key' => '']);
        $this->assertFalse($client->isConfigured());
    }

    public function test_throws_exception_when_chatting_without_api_key()
    {
        $this->expectException(LlmException::class);
        $client = new KimiLlmClient(['key' => '']);
        $client->chat('System prompt', [['role' => 'user', 'content' => 'Olá']]);
    }

    public function test_sends_chat_completion_request_to_kimi_api()
    {
        Http::fake([
            'https://api.moonshot.cn/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Olá! Eu sou o assistente Kimi IA.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new KimiLlmClient([
            'key' => 'test-kimi-key',
            'base_url' => 'https://api.moonshot.cn/v1',
            'model' => 'moonshot-v1-8k',
        ]);

        $resposta = $client->chat('Instruções', [['role' => 'user', 'content' => 'Qual é o teu nome?']]);

        $this->assertEquals('Olá! Eu sou o assistente Kimi IA.', $resposta);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.moonshot.cn/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-kimi-key')
                && $request['model'] === 'moonshot-v1-8k';
        });
    }
}
