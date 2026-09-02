<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Não aplicar CSP estrito ou headers de conteúdo a responses que não sejam HTTP padrão
        if (! method_exists($response, 'header')) {
            return $response;
        }

        // 1. Prevenção de MIME Sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // 2. Proteção contra Clickjacking (Frame Embedding)
        $response->header('X-Frame-Options', 'SAMEORIGIN');

        // 3. Política de Referrer
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 4. Desabilita APIs de hardware e recursos não utilizados no browser
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // 5. Modern XSS Filter (desativa filtro legado que introduzia falhas, confiando em CSP e encoding)
        $response->header('X-XSS-Protection', '0');

        // 6. HSTS (HTTP Strict Transport Security) - ativo quando HTTPS
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https' || app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // 7. Content Security Policy (CSP)
        // Permite recursos locais e CDNs corporativos utilizados pela plataforma (Bootstrap, FontAwesome, TinyMCE, Chart.js)
        // Permite WebSockets do Laravel Reverb (ws: wss:)
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' ws: wss: https:",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Se for download ou visualização de PDF/stream, remove object-src restriction localmente se necessário
        $response->header('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
