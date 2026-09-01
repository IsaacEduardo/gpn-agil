@extends('layouts.guest')

@section('title', 'Portal Institucional')

@section('styles')
    <style>
        /* Top bar institucional */
        .portal-topbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-soft);
            padding: 0.85rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .portal-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            text-decoration: none;
        }

        .portal-brand img { height: 44px; width: auto; }

        .portal-brand .pb-name {
            font-family: var(--font-heading);
            font-weight: 800;
            color: #1e293b;
            font-size: 1.05rem;
            line-height: 1.1;
        }

        .portal-brand .pb-sub {
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        /* Hero claro e sóbrio */
        .portal-hero {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(900px 420px at 85% -10%, rgba(37, 99, 235, 0.08) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, var(--bg-body) 100%);
            padding: 5.5rem 0 4.5rem;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.18);
            color: var(--primary-accent);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 0.4rem 1rem;
            border-radius: 999px;
        }

        .hero-title {
            font-weight: 800;
            font-size: clamp(2rem, 5vw, 3.25rem);
            color: #0f172a;
            line-height: 1.12;
            margin: 1.25rem 0;
        }

        .hero-title .accent { color: var(--primary-accent); }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 640px;
            margin: 0 auto 2.25rem;
        }

        /* Cartões de módulos */
        .modules-section { padding: 1rem 0 5rem; }

        .module-card {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 2rem 1.75rem;
            height: 100%;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }

        .module-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-card);
            border-color: rgba(37, 99, 235, 0.25);
        }

        .module-icon {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-md);
            background: var(--primary-soft);
            color: var(--primary-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            margin-bottom: 1.25rem;
            transition: background-color .25s ease, color .25s ease;
        }

        .module-card:hover .module-icon {
            background: var(--primary-accent);
            color: #fff;
        }

        .module-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .module-text {
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 0;
        }

        .section-heading { color: #0f172a; font-weight: 800; }
    </style>
@endsection

@section('content')
    <!-- Top bar institucional -->
    <header class="portal-topbar">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ url('/') }}" class="portal-brand">
                <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia institucional">
                <span>
                    <span class="pb-name d-block">{{ $dadosInstituicao->sigla ?? 'GOV' }}</span>
                    <span class="pb-sub">{{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial' }}</span>
                </span>
            </a>
            <div>
                @auth
                    <a href="{{ route('home') }}" class="btn btn-institucional">
                        <i class="fas fa-gauge-high me-2"></i>Ir para o Painel
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-institucional">
                        <i class="fas fa-right-to-bracket me-2"></i>Aceder ao Sistema
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="portal-hero text-center">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <span class="hero-pill">
                        <i class="fas fa-shield-halved"></i>
                        {{ $dadosInstituicao->cabecalho_linha1 ?? 'República de Angola' }}
                    </span>
                    <h1 class="hero-title">
                        Sistema de Gestão <span class="accent">Administrativa e Documental</span>
                    </h1>
                    <p class="hero-subtitle">
                        Plataforma institucional do {{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial' }}
                        para a gestão de documentos, requisições, frota e serviços — de forma segura, rastreável e eficiente.
                    </p>
                    <div class="d-flex justify-content-center flex-wrap gap-3">
                        @auth
                            <a href="{{ route('home') }}" class="btn btn-institucional">
                                <i class="fas fa-gauge-high me-2"></i>Aceder ao Painel
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-institucional">
                                <i class="fas fa-right-to-bracket me-2"></i>Aceder ao Sistema
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Módulos do sistema -->
    <section class="modules-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-heading h3 mb-2">Módulos do Sistema</h2>
                <p class="text-muted mb-0">Recursos integrados para uma gestão pública moderna</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-file-signature"></i></div>
                        <h3 class="module-title">Gestão Documental</h3>
                        <p class="module-text">Registo, protocolo, encaminhamento e arquivo de documentos de entrada e internos, com rastreio de despachos e prazos.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-clipboard-check"></i></div>
                        <h3 class="module-title">Requisições</h3>
                        <p class="module-text">Fluxo de aprovação para produtos, oficina, serviços e passagens, com vistos de departamento e assinatura.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-truck-fast"></i></div>
                        <h3 class="module-title">Frota e Viaturas</h3>
                        <p class="module-text">Controlo do estado operacional, histórico de manutenção e documentação de toda a frota institucional.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-calendar-check"></i></div>
                        <h3 class="module-title">Reservas de Espaços</h3>
                        <p class="module-text">Agendamento de espaços com calendário, verificação de disponibilidade e aprovação por departamento.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-shield-halved"></i></div>
                        <h3 class="module-title">Segurança e Acesso</h3>
                        <p class="module-text">Controlo de acesso por funções (RBAC), delegação de poderes e trilha de auditoria detalhada.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card">
                        <div class="module-icon"><i class="fas fa-headset"></i></div>
                        <h3 class="module-title">Suporte</h3>
                        <p class="module-text">Canal direto de feedback e apoio para garantir a continuidade do serviço às equipas.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
