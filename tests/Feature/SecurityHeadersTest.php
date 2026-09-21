<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_in_responses()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->assertHeader('X-XSS-Protection', '0');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }

    /**
     * O agente de digitalização vive em http://127.0.0.1:18090, que nem 'self'
     * nem https: cobrem. Sem esta entrada o browser bloqueia o pedido antes de
     * sair e o scanner aparece permanentemente offline.
     */
    public function test_csp_autoriza_o_agente_local_de_digitalizacao(): void
    {
        config(['webscan.agent_url' => 'http://127.0.0.1:18090']);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $connectSrc = collect(explode('; ', $csp))->first(fn ($d) => str_starts_with($d, 'connect-src'));

        $this->assertStringContainsString('http://127.0.0.1:18090', $connectSrc);
    }

    /** Uma configuração vazia ou malformada não pode injetar lixo na política. */
    public function test_csp_ignora_url_de_agente_invalida(): void
    {
        config(['webscan.agent_url' => 'isto-nao-e-um-url']);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $connectSrc = collect(explode('; ', $csp))->first(fn ($d) => str_starts_with($d, 'connect-src'));

        $this->assertSame("connect-src 'self' ws: wss: https:", $connectSrc);
    }
}
