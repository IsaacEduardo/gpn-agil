<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $dadosInstituicao->sigla ?? 'GPN' }} — @yield('title', 'Portal Institucional')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/ondaka-favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/ondaka-favicon-16.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/ondaka-favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/ondaka-favicon-192.png') }}">

    <!-- Vite Assets (Bootstrap CSS + JS + Tema institucional) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS (alto contraste/acessibilidade) -->
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    <style>
        :root {
            --primary-bg: #333333;
            --primary-accent: #CE1126;        /* Vermelho institucional (Angola) */
            --primary-accent-hover: #A50E1F;
            --primary-soft: #FDECEE;          /* Vermelho 50 — realces suaves */
            --text-main: #333333;
            --text-muted: #777777;
            --bg-body: #F5F5F5;
            --border-soft: #CCCCCC;
            --font-heading: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-body: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --shadow-soft: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -12px rgba(15, 23, 42, 0.12);
            --shadow-card: 0 1px 3px rgba(15, 23, 42, 0.05), 0 16px 40px -20px rgba(15, 23, 42, 0.18);
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6, .brand-text {
            font-family: var(--font-heading);
        }

        .guest-main {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
        }

        /* Acento institucional reutilizável */
        .text-accent { color: var(--primary-accent) !important; }
        .bg-soft { background-color: var(--primary-soft) !important; }

        .btn-institucional {
            background: var(--primary-accent);
            border: 1px solid var(--primary-accent);
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 4px 12px rgba(206, 17, 38, 0.3);
        }

        .btn-institucional:hover {
            background: var(--primary-accent-hover);
            border-color: var(--primary-accent-hover);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(206, 17, 38, 0.4);
        }

        .btn-institucional-outline {
            background: transparent;
            border: 1px solid var(--border-soft);
            color: var(--text-main);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            transition: all .2s ease;
        }

        .btn-institucional-outline:hover {
            border-color: var(--primary-accent);
            color: var(--primary-accent);
            background: var(--primary-soft);
        }

        /* Rodapé institucional slim */
        .guest-footer {
            flex-shrink: 0;
            background: #ffffff;
            border-top: 1px solid var(--border-soft);
            padding: 1.25rem 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .guest-footer a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color .2s;
        }

        .guest-footer a:hover { color: var(--primary-accent); }

        /* Acessibilidade: foco visível por teclado */
        a:focus-visible,
        button:focus-visible,
        .form-control:focus-visible,
        .btn:focus-visible {
            outline: 3px solid rgba(206, 17, 38, 0.45);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.001ms !important;
                transition-duration: 0.001ms !important;
            }
        }
    </style>
    @yield('styles')
</head>

<body>
    <div class="guest-main">
        @yield('content')
    </div>

    <footer class="guest-footer">
        <div class="container">
            <div class="row align-items-center gy-2">
                <div class="col-md-6 text-center text-md-start">
                    <span class="fw-semibold" style="color: var(--text-main);">
                        {{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial do Namibe' }}
                    </span>
                    @if (!empty($dadosInstituicao->cidade))
                        <span class="d-none d-md-inline"> · {{ $dadosInstituicao->cidade }}</span>
                    @endif
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span>&copy; {{ date('Y') }} · Fornecido por
                        <a href="#" class="fw-semibold">Ondaka</a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    @yield('scripts')
</body>

</html>
