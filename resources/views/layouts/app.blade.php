<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Ondaka - @yield('title', 'Gestão Inteligente')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/ondaka-favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/ondaka-favicon-16.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/ondaka-favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/ondaka-favicon-192.png') }}">

    <!-- Vite Assets (Bootstrap CSS + JS + Tema institucional) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Plugins CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css">
    @stack('lightbox_styles')

    <!-- Custom CSS -->
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    <style>
        /* Tokens institucionais (mantêm os nomes legados; valores agora em verde) */
        :root {
            --primary-bg: #333333;
            --primary-accent: #CE1126;        /* Vermelho institucional (Angola) */
            --primary-accent-hover: #A50E1F;

            --text-main: #333333;
            --text-muted: #777777;

            --bg-body: #F5F5F5;
            --bg-card: #FFFFFF;

            --transition-speed: 0.2s;

            --font-heading: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-body: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;

            --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);

            --radius-md: 0.25rem;
            --radius-lg: 0.375rem;
            --radius-xl: 0.5rem;
        }

        /* Utilitários legados (agora derivam do verde institucional) */
        .text-accent { color: var(--primary-accent); }
        .bg-accent { background-color: var(--primary-accent); }
    </style>
    @yield('styles')
</head>

<body class="gov-app">
    {{-- Toast Portal & Engine Universal --}}
    @include('components.toast-container')

    @auth
        @include('layouts.partials.gov-header')
    @endauth

    <!-- Conteúdo -->
    <main class="gov-main">
        <div class="gov-container">
            @yield('breadcrumbs')
            @yield('content')
        </div>
    </main>

    <footer class="gov-footer">
        <div class="gov-footer__inner">
            &copy; {{ date('Y') }} {{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial' }} &mdash; {{ date('d/m/Y') }}
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

    <script>
        // NProgress
        window.addEventListener('beforeunload', () => NProgress.start());
        $(document).ajaxStart(() => NProgress.start());
        $(document).ajaxStop(() => NProgress.done());

        // Pesquisa global (funcionalidade inalterada)
        const searchInput = document.querySelector('input[name="q"]');
        const searchResults = document.getElementById('searchResults');
        let debounceTimer;

        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function(e) {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }

                debounceTimer = setTimeout(() => {
                    searchResults.style.display = 'block';
                    searchResults.innerHTML =
                        '<div class="p-3 text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Buscando...</div>';

                    fetch(`/global-search?q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                searchResults.innerHTML =
                                    '<div class="p-3 text-center text-muted">Nenhum resultado encontrado.</div>';
                            } else {
                                let html = '';
                                let currentCategory = '';

                                data.forEach(item => {
                                    if (item.category !== currentCategory) {
                                        if (currentCategory !== '') html +=
                                            '<div class="dropdown-divider"></div>';
                                        html +=
                                            `<h6 class="dropdown-header text-uppercase small fw-bold mt-1">${item.category}</h6>`;
                                        currentCategory = item.category;
                                    }

                                    html += `
                                        <a href="${item.url}" class="dropdown-item py-2 d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-light p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="fas ${item.icon} text-primary small"></i>
                                            </div>
                                            <span class="text-truncate">${item.label}</span>
                                        </a>
                                    `;
                                });
                                searchResults.innerHTML = html;
                            }
                        })
                        .catch(error => {
                            console.error('Erro na busca:', error);
                            searchResults.innerHTML =
                                '<div class="p-3 text-center text-danger">Erro ao buscar. Tente novamente.</div>';
                        });
                }, 300);
            });

            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });

            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2 && searchResults.innerHTML !== '') {
                    searchResults.style.display = 'block';
                }
            });
        }

        // Auto-dismiss alerts with a smooth fade-out and slide-up animation after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    // Smooth transition styles
                    alert.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    
                    // Remove from DOM after the transition is complete
                    setTimeout(() => {
                        if (window.bootstrap && window.bootstrap.Alert) {
                            const bsAlert = window.bootstrap.Alert.getOrCreateInstance(alert);
                            if (bsAlert) {
                                bsAlert.close();
                            } else {
                                alert.remove();
                            }
                        } else {
                            alert.remove();
                        }
                    }, 500);
                });
            }, 5000); // 5 seconds delay
        });

        // UX P4: Global Keyboard Shortcuts (Ctrl+S / Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                const saveBtn = document.querySelector('button[type="submit"], #saveDraftBtn, #saveBtn');
                if (saveBtn) {
                    saveBtn.click();
                    window.showToast('Guardado rápido acionado via teclado!', 'success');
                }
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
                const pdfBtn = document.querySelector('#openPdfBtn, #printPdfBtn, .btn-print-pdf');
                if (pdfBtn) {
                    e.preventDefault();
                    pdfBtn.click();
                }
            }
        });
    </script>
    @yield('scripts')
    @stack('scripts')
</body>

</html>
