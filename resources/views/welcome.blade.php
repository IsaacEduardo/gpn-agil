<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>GPN-AGIL - Sistema de Gestão de Logística e Patrimônio</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- Custom CSS -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-color: #0f172a; /* Slate 900 */
                --secondary-color: #334155; /* Slate 700 */
                --accent-color: #2563eb; /* Blue 600 */
                --light-color: #f8fafc; /* Slate 50 */
                --dark-color: #0f172a; /* Slate 900 */
                --text-muted: #64748b;
            }
            
            body {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                background-color: var(--light-color);
                color: #1e293b;
                line-height: 1.6;
            }
            
            .navbar {
                background-color: rgba(255, 255, 255, 0.95) !important;
                backdrop-filter: blur(10px);
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            .navbar-brand {
                font-weight: 700;
                color: var(--primary-color) !important;
                font-size: 1.5rem;
                letter-spacing: -0.5px;
            }
            
            .nav-link {
                color: var(--secondary-color) !important;
                font-weight: 500;
                margin-left: 1rem;
            }

            .nav-link:hover {
                color: var(--accent-color) !important;
            }
            
            .hero-section {
                background: var(--primary-color);
                color: white;
                padding: 8rem 0 6rem;
                position: relative;
                overflow: hidden;
            }

            .hero-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: radial-gradient(circle at top right, #1e293b, transparent 60%);
                opacity: 0.5;
            }
            
            .hero-title {
                font-size: 3.5rem;
                font-weight: 800;
                letter-spacing: -1px;
                margin-bottom: 1.5rem;
                line-height: 1.1;
            }
            
            .hero-subtitle {
                font-size: 1.25rem;
                margin-bottom: 2.5rem;
                color: #cbd5e1;
                font-weight: 300;
                max-width: 600px;
            }
            
            .feature-card {
                border: none;
                border-radius: 16px;
                background: white;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
                transition: all 0.3s ease;
                height: 100%;
                padding: 2rem;
            }
            
            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            }
            
            .feature-icon {
                width: 48px;
                height: 48px;
                background: #eff6ff;
                color: var(--accent-color);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .feature-title {
                font-weight: 700;
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
                color: var(--primary-color);
            }

            .feature-text {
                color: var(--text-muted);
            }
            
            .footer {
                background-color: white;
                color: var(--secondary-color);
                padding: 4rem 0 2rem;
                border-top: 1px solid #e2e8f0;
            }
            
            .btn-primary {
                background-color: var(--accent-color);
                border-color: var(--accent-color);
                padding: 0.75rem 2rem;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.2s;
            }

            .btn-primary:hover {
                background-color: #1d4ed8;
                border-color: #1d4ed8;
                transform: translateY(-1px);
            }
            
            .btn-outline-light {
                border: 1px solid rgba(255,255,255,0.2);
                padding: 0.75rem 2rem;
                font-weight: 600;
                border-radius: 8px;
            }

            .btn-outline-light:hover {
                background: rgba(255,255,255,0.1);
                color: white;
                border-color: white;
            }
        </style>
    </head>
    <body>
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                    <img src="{{ asset('images/insignia.png') }}" alt="GPN-AGIL" height="32" class="d-inline-block align-text-top">
                    GPN-AGIL
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        @if (Route::has('login'))
                            @auth
                                <li class="nav-item">
                                    <a href="{{ url('/home') }}" class="nav-link">Dashboard</a>
                                </li>
                            @else
                                <li class="nav-item">
                                    <a href="{{ route('login') }}" class="nav-link">Login</a>
                                </li>
                                @if (Route::has('register'))
                                    <li class="nav-item">
                                        <a href="{{ route('register') }}" class="nav-link">Registrar</a>
                                    </li>
                                @endif
                            @endauth
                        @endif
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container text-center">
                <h1 class="hero-title">GPN-AGIL</h1>
                <p class="hero-subtitle">Sistema de Gestão de Logística e Patrimônio</p>
                <div class="d-flex justify-content-center gap-3">
                    @auth
                        <a href="{{ route('home') }}" class="btn btn-outline-light">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-light me-2">Login</a>
                        <a href="{{ route('register') }}" class="btn btn-light">Registrar</a>
                    @endauth
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="container my-5">
            <h2 class="text-center mb-5">Funcionalidades Principais</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-car feature-icon"></i>
                            <h3 class="h4 mb-3">Gestão de Viaturas</h3>
                            <p class="text-muted">Cadastro completo de viaturas com status operacional, histórico de manutenção e documentação.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-file-alt feature-icon"></i>
                            <h3 class="h4 mb-3">Requisições Personalizadas</h3>
                            <p class="text-muted">Templates específicos para Produtos, Oficina e Serviços Gerais com campos customizados.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-file-pdf feature-icon"></i>
                            <h3 class="h4 mb-3">Termos de Entrega</h3>
                            <p class="text-muted">Geração automática de termos de entrega e devolução em formato PDF.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-lock feature-icon"></i>
                            <h3 class="h4 mb-3">Segurança Avançada</h3>
                            <p class="text-muted">Autenticação segura e controle de acesso granular para proteger dados sensíveis.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-chart-line feature-icon"></i>
                            <h3 class="h4 mb-3">Relatórios e Estatísticas</h3>
                            <p class="text-muted">Visualização de dados em tempo real para tomada de decisões estratégicas.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="text-center">
                            <i class="fas fa-comments feature-icon"></i>
                            <h3 class="h4 mb-3">Feedback e Melhorias</h3>
                            <p class="text-muted">Sistema de coleta de feedback dos usuários para melhoria contínua da plataforma.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h4>GPN-AGIL</h4>
                        <p>Sistema de Gestão de Logística e Patrimônio desenvolvido para otimizar as operações do departamento.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p>&copy; {{ date('Y') }} GPN-AGIL. Todos os direitos reservados.</p>
                        <p>Versão 1.0</p>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
