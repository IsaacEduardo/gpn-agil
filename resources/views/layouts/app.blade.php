<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>GPN-AGIL - @yield('title', 'Sistema de Gestão de Logística e Patrimônio')</title>

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css">
    @stack('lightbox_styles')
    <!-- Custom CSS -->
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">

    <style>
        :root {
            /* Professional Corporate Palette */
            --brand-primary: #0f172a;
            /* Slate 900 */
            --brand-secondary: #334155;
            /* Slate 700 */
            --brand-accent: #2563eb;
            /* Blue 600 */
            --brand-success: #059669;
            /* Emerald 600 */
            --brand-warning: #d97706;
            /* Amber 600 */
            --brand-danger: #dc2626;
            /* Red 600 */

            --neutral-900: #0f172a;
            --neutral-800: #1e293b;
            --neutral-600: #475569;
            --neutral-400: #94a3b8;
            --neutral-200: #e2e8f0;
            --neutral-100: #f1f5f9;
            --neutral-50: #f8fafc;
            --neutral-0: #ffffff;

            --surface-1: var(--neutral-0);
            --surface-2: var(--neutral-50);
            --text-1: var(--neutral-800);
            --text-2: var(--neutral-600);

            --radius: 8px;
            --radius-lg: 12px;

            --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);

            --bs-primary: #0f172a;
            --bs-primary-rgb: 15, 23, 42;
            --bs-secondary: #64748b;
            --bs-secondary-rgb: 100, 116, 139;
            --bs-danger: #dc2626;
            --bs-danger-rgb: 220, 38, 38;
            --bs-success: #059669;
            --bs-success-rgb: 5, 150, 105;
        }

        [data-bs-theme="dark"] {
            --surface-1: #1e293b;
            --surface-2: #0f172a;
            --text-1: #f1f5f9;
            --text-2: #cbd5e1;
            --neutral-200: #334155;
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, .5);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .6);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-1);
            background: var(--surface-2);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.5;
        }

        /* Sidebar Modernization */
        .sidebar {
            width: 280px;
            height: 100vh;
            overflow: hidden;
            transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(180deg, var(--brand-primary) 0%, #020617 100%);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.05);
            z-index: 1030;
        }

        /* Custom Scrollbar for Sidebar */
        .custom-scrollbar {
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }

        .sidebar .nav-link {
            color: #94a3b8;
            border-radius: var(--radius);
            padding: .75rem 1rem;
            margin: 0.25rem 0.75rem;
            position: relative;
            transition: all .2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link i {
            width: 1.5rem;
            text-align: center;
            margin-right: 0.75rem;
            transition: transform .2s ease;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(4px);
        }

        .sidebar .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #fff;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background: linear-gradient(90deg, var(--brand-accent) 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .sidebar .nav-link.active i {
            transform: scale(1.1);
        }

        .sidebar .nav-section {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            padding: 1.5rem 1.25rem 0.5rem;
            letter-spacing: .08em;
        }

        .border-white-10 {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        .user-profile-section {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-bar {
            height: 70px;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 1rem;
        }

        /* Modern Cards */
        .card {
            border: none;
            border-radius: var(--radius-lg);
            background: var(--surface-1);
            box-shadow: var(--shadow-sm);
            transition: transform .2s ease, box-shadow .2s ease;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--neutral-100);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Refined Tables */
        .table-responsive {
            border-radius: var(--radius);
            box-shadow: var(--shadow-xs);
            background: var(--surface-1);
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            background: var(--neutral-50);
            color: var(--neutral-600);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--neutral-200);
        }

        .table tbody td {
            padding: 1rem 1.5rem;
            color: var(--text-1);
            border-bottom: 1px solid var(--neutral-100);
            font-size: 0.95rem;
        }

        .table-hover tbody tr:hover {
            background-color: var(--neutral-50);
        }

        /* Buttons & Controls */
        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: var(--radius);
            transition: all .2s;
        }

        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            box-shadow: var(--shadow-xs);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--brand-secondary);
            border-color: var(--brand-secondary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .form-control,
        .form-select {
            border-radius: var(--radius);
            border: 1px solid var(--neutral-200);
            padding: 0.625rem 1rem;
            font-size: 0.95rem;
            background-color: var(--surface-1);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Topbar refinement */
        .topbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--neutral-200);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Sidebar Responsive Logic */
        @media (min-width: 992px) {
            .content-area {
                margin-left: 280px;
                transition: margin-left .3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-collapsed .sidebar {
                width: 80px;
            }

            .sidebar-collapsed .content-area {
                margin-left: 80px;
            }

            .sidebar-collapsed .sidebar .brand span,
            .sidebar-collapsed .sidebar .nav-section,
            .sidebar-collapsed .sidebar .nav-link span,
            .sidebar-collapsed .sidebar .nav-link .badge,
            .sidebar-collapsed .sidebar .nav-link .fa-chevron-down {
                display: none !important;
            }

            .sidebar-collapsed .sidebar .brand {
                justify-content: center;
                padding: 1rem 0 !important;
            }

            .sidebar-collapsed .sidebar .nav-link {
                justify-content: center;
                padding: 0.75rem;
            }

            .sidebar-collapsed .sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.25rem;
            }

            .sidebar-hidden .sidebar {
                transform: translateX(-100%);
            }

            .sidebar-hidden .content-area {
                margin-left: 0;
            }
        }

        @media (max-width: 991.98px) {
            .content-area {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        .btn-secondary {
            background: var(--neutral-200);
            color: var(--brand-primary);
            border-color: var(--neutral-200);
        }

        .btn-secondary:hover {
            background: color-mix(in srgb, var(--neutral-200) 92%, white);
        }

        .bg-gradient-primary {
            background-image: linear-gradient(90deg, rgba(var(--bs-primary-rgb), .90), rgba(var(--bs-primary-rgb), .70));
            color: #fff;
        }

        /* Toasts */
        .toast {
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .toast .toast-body {
            font-weight: 500;
        }

        /* Footer */
        footer {
            border-top: 1px solid rgba(0, 0, 0, .08);
        }

        [data-bs-theme="dark"] footer {
            border-top: 1px solid rgba(255, 255, 255, .12);
        }

        /* High contrast support tweaks */
        .contrast-high .sidebar .nav-link.active,
        .contrast-high .sidebar .nav-link:hover {
            background-color: #000;
        }

        .contrast-high :focus-visible {
            outline: 3px solid #fff !important;
            outline-offset: 2px;
        }

        /* Focus ring for keyboard navigation */
        :focus-visible {
            outline: 3px solid rgba(var(--bs-primary-rgb), .45);
            outline-offset: 2px;
            border-radius: 8px;
        }

        .form-control {
            border-color: var(--neutral-400);
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15);
        }

        label {
            opacity: 1;
        }

        .dashboard-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
        }

        .hero-title {
            font-weight: 700;
            letter-spacing: .2px;
        }

        .section-title {
            font-weight: 600;
            color: var(--brand-primary);
            letter-spacing: .2px;
        }

        .visually-hidden-focusable:not(:focus) {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .visually-hidden-focusable:focus {
            position: fixed;
            top: 8px;
            left: 8px;
            z-index: 2000;
            background: var(--bs-primary);
            color: #fff;
            padding: .5rem .75rem;
            border-radius: .5rem;
            box-shadow: var(--shadow-sm);
        }
    </style>

    @yield('styles')
</head>

<body>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>
    <a href="#mainContent" class="visually-hidden-focusable skip-link">Ir para conteúdo</a>
    <div class="d-flex min-vh-100">
        <!-- Sidebar (Desktop) -->
        <aside id="desktopSidebar"
            class="sidebar text-white position-fixed top-0 start-0 d-none d-lg-flex flex-column vh-100 shadow-lg">
            <div class="brand d-flex align-items-center px-4 py-3 border-bottom border-white-10">
                <a class="text-white text-decoration-none d-flex align-items-center gap-2" href="{{ route('home') }}">
                    <img src="{{ asset('images/insignia.png') }}" alt="Insígnia" style="height:32px;">
                    <span class="fw-bold tracking-wide">GPN-AGIL</span>
                </a>
            </div>
            <nav class="flex-grow-1 px-3 py-4 custom-scrollbar">
                <ul class="nav nav-pills flex-column gap-2">
                    @auth
                        <div class="nav-section">Operações</div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"
                                title="Dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('viaturas.*') ? 'active' : '' }}"
                                href="{{ route('viaturas.index') }}" title="Viaturas">
                                <i class="fas fa-car me-2"></i>Viaturas
                            </a>
                        </li>
                        @can('viewAny', App\Models\Requisicao::class)
                            <li class="nav-item">
                                <a class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('requisicoes.*') ? 'active' : '' }}"
                                    title="Requisições" data-bs-toggle="collapse" href="#sbRequisicoes" role="button"
                                    aria-expanded="false" aria-controls="sbRequisicoes">
                                    <span><i class="fas fa-file-alt me-2"></i>Requisições</span>
                                    <i class="fas fa-chevron-down small"></i>
                                </a>
                                <div class="collapse {{ request()->routeIs('requisicoes.*') ? 'show' : '' }}"
                                    id="sbRequisicoes">
                                    <ul class="nav flex-column ms-3 my-2">
                                        <li class="nav-item">
                                            <a class="nav-link {{ request()->routeIs('requisicoes.index') ? 'active' : '' }}"
                                                title="Requisições: Todas" href="{{ route('requisicoes.index') }}">
                                                <i class="fas fa-list me-2"></i>Todas
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link {{ request()->routeIs('requisicoes.pendentes') ? 'active' : '' }}"
                                                title="Requisições: Pendentes de Aprovação"
                                                href="{{ route('requisicoes.pendentes') }}">
                                                <i class="fas fa-check-double me-2"></i>Pendentes
                                                @if (
                                                    !empty($menuCounts) &&
                                                        ($menuCounts['can_review_requisicoes'] ?? false) &&
                                                        ($menuCounts['pend_requisicoes'] ?? 0) > 0)
                                                    <span
                                                        class="badge rounded-pill ms-auto">{{ $menuCounts['pend_requisicoes'] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('requisicoes.produtos.index') ? 'active' : '' }}"
                                                title="Requisições: Produtos"
                                                href="{{ route('requisicoes.produtos.index') }}"><i
                                                    class="fas fa-box-open me-2"></i>Produtos</a></li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('requisicoes.oficina.index') ? 'active' : '' }}"
                                                title="Requisições: Oficina" href="{{ route('requisicoes.oficina.index') }}"><i
                                                    class="fas fa-wrench me-2"></i>Oficina</a></li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('requisicoes.servico.index') ? 'active' : '' }}"
                                                title="Requisições: Serviços"
                                                href="{{ route('requisicoes.servico.index') }}"><i
                                                    class="fas fa-briefcase me-2"></i>Serviços</a></li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('requisicoes.passagem.index') ? 'active' : '' }}"
                                                title="Requisições: Passagem"
                                                href="{{ route('requisicoes.passagem.index') }}"><i
                                                    class="fas fa-ticket-alt me-2"></i>Passagem</a></li>
                                        @can('create', App\Models\Requisicao::class)
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('requisicoes.create') ? 'active' : '' }}"
                                                    href="{{ route('requisicoes.create', ['tipo' => 'produto']) }}"><i
                                                        class="fas fa-box-open me-2"></i>Nova de Produtos</a></li>
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('requisicoes.create') ? 'active' : '' }}"
                                                    href="{{ route('requisicoes.create', ['tipo' => 'oficina']) }}"><i
                                                        class="fas fa-wrench me-2"></i>Nova de Oficina</a></li>
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('requisicoes.create') ? 'active' : '' }}"
                                                    href="{{ route('requisicoes.create', ['tipo' => 'servico']) }}"><i
                                                        class="fas fa-briefcase me-2"></i>Nova de Serviços</a></li>
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('requisicoes.passagem.create.novo') ? 'active' : '' }}"
                                                    href="{{ route('requisicoes.passagem.create.novo') }}"><i
                                                        class="fas fa-ticket-alt me-2"></i>Nova de Passagem</a></li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                        @endcan
                        <div class="nav-section">Documentos</div>
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') !== 'pendentes_recebimento' ? 'active' : '' }}"
                                href="{{ route('documentos-entradas.index') }}" title="Entradas"><i
                                    class="fas fa-inbox me-2"></i>Entradas</a></li>
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'pendentes_recebimento' ? 'active' : '' }}"
                                href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento']) }}"
                                title="Por Receber"><i class="fas fa-hourglass-half me-2"></i>Por Receber
                                @if (!empty($menuCounts) && ($menuCounts['pend_documentos_por_receber'] ?? 0) > 0)
                                    <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                            class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_por_receber'] }}</span>
                                @endif
                            </a></li>
                        <li class="nav-item">
                            <a class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado']) ? 'active' : '' }}"
                                title="Vistos do Departamento" data-bs-toggle="collapse" href="#sbVistosDept"
                                role="button" aria-expanded="false" aria-controls="sbVistosDept">
                                <span><i class="fas fa-eye me-2"></i>Vistos Departamento</span>
                                <i class="fas fa-chevron-down small"></i>
                            </a>
                            <div class="collapse {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado']) ? 'show' : '' }}"
                                id="sbVistosDept">
                                <ul class="nav flex-column ms-3 my-2">
                                    <li class="nav-item"><a
                                            class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_pendente' ? 'active' : '' }}"
                                            href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}">
                                            Pendente
                                            @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                                                <span
                                                    class="badge rounded-pill ms-auto">{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                                            @endif
                                        </a></li>
                                    <li class="nav-item"><a
                                            class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_aprovado' ? 'active' : '' }}"
                                            href="{{ route('documentos-entradas.index', ['meus' => 'visto_aprovado']) }}">
                                            Aprovado
                                            @if (!empty($menuCounts) && ($menuCounts['aprov_documentos_visto_departamento'] ?? 0) > 0)
                                                <span
                                                    class="badge rounded-pill ms-auto">{{ $menuCounts['aprov_documentos_visto_departamento'] }}</span>
                                            @endif
                                        </a></li>
                                    <li class="nav-item"><a
                                            class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_rejeitado' ? 'active' : '' }}"
                                            href="{{ route('documentos-entradas.index', ['meus' => 'visto_rejeitado']) }}">
                                            Rejeitado
                                            @if (!empty($menuCounts) && ($menuCounts['rej_documentos_visto_departamento'] ?? 0) > 0)
                                                <span
                                                    class="badge rounded-pill ms-auto">{{ $menuCounts['rej_documentos_visto_departamento'] }}</span>
                                            @endif
                                        </a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_pendente' ? 'active' : '' }}"
                                href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}"
                                title="Visto Departamento Pendente"><i class="fas fa-eye me-2"></i>Visto Departamento
                                Pendente
                                @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                                    <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                            class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                                @endif
                            </a></li>
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_gabinete_pendente' ? 'active' : '' }}"
                                href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente']) }}"
                                title="Visto Gabinete Pendente"><i class="fas fa-user-tie me-2"></i>Visto Gabinete
                                Pendente
                                @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_gabinete'] ?? 0) > 0)
                                    <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                            class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_visto_gabinete'] }}</span>
                                @endif
                            </a></li>
                        @can('create', App\Models\DocumentoEntrada::class)
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('documentos-entradas.create') ? 'active' : '' }}"
                                    href="{{ route('documentos-entradas.create') }}" title="Nova Entrada"><i
                                        class="fas fa-plus-circle me-2"></i>Nova Entrada</a></li>
                        @endcan
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pastas.*') ? 'active' : '' }}"
                                href="{{ route('pastas.index') }}" title="Arquivo">
                                <i class="fas fa-folder me-2"></i>Arquivo (Pastas)
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('termos.*') ? 'active' : '' }}"
                                href="{{ route('termos.index') }}" title="Termos"><i
                                    class="fas fa-file-signature me-2"></i>Termos</a>
                        </li>
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('credenciais.*') ? 'active' : '' }}"
                                href="{{ route('credenciais.index') }}" title="Credenciais"><i
                                    class="fas fa-id-card me-2"></i>Credenciais</a>
                        </li>
                        @can('viewAny', App\Models\ReservaEspaco::class)
                            <li class="nav-item">
                                <a class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('reservas.*') ? 'active' : '' }}"
                                    title="Reservas" data-bs-toggle="collapse" href="#sbReservas" role="button"
                                    aria-expanded="false" aria-controls="sbReservas">
                                    <span><i class="fas fa-calendar-alt me-2"></i>Reservas</span>
                                    <i class="fas fa-chevron-down small"></i>
                                </a>
                                <div class="collapse {{ request()->routeIs('reservas.*') ? 'show' : '' }}" id="sbReservas">
                                    <ul class="nav flex-column ms-3 my-2">
                                        <li class="nav-item">
                                            <a class="nav-link {{ request()->routeIs('reservas.index') ? 'active' : '' }}"
                                                href="{{ route('reservas.index') }}">
                                                <i class="fas fa-list me-2"></i>Todas
                                                @if (!empty($menuCounts) && ($menuCounts['can_review_reservas'] ?? false) && ($menuCounts['pend_reservas'] ?? 0) > 0)
                                                    <span
                                                        class="badge rounded-pill ms-auto">{{ $menuCounts['pend_reservas'] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('reservas.calendar') ? 'active' : '' }}"
                                                href="{{ route('reservas.calendar') }}"><i
                                                    class="fas fa-calendar-day me-2"></i>Calendário</a></li>
                                        @can('create', App\Models\ReservaEspaco::class)
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('reservas.create') ? 'active' : '' }}"
                                                    href="{{ route('reservas.create') }}"><i
                                                        class="fas fa-plus-circle me-2"></i>Nova Reserva</a></li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                        @endcan

                        <div class="nav-section">Estrutura</div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('empresas.*') ? 'active' : '' }}"
                                href="{{ route('empresas.index') }}" title="Empresas"><i
                                    class="fas fa-building me-2"></i>Empresas</a>
                        </li>
                        @if (Auth::user()->role && Auth::user()->role->name === 'admin')
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}"
                                    href="{{ route('gabinetes.index') }}" title="Gabinetes"><i
                                        class="fas fa-building-columns me-2"></i>Gabinetes</a></li>
                        @endif
                        <li class="nav-item"><a
                                class="nav-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}"
                                href="{{ route('departamentos.index') }}" title="Departamentos"><i
                                    class="fas fa-sitemap me-2"></i>Departamentos</a></li>
                        @if (Auth::user()->role && Auth::user()->role->name === 'admin')
                            <div class="nav-section">Administração</div>
                            <li class="nav-item"><a
                                    class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                                    title="Administração" data-bs-toggle="collapse" href="#sbAdmin" role="button"
                                    aria-expanded="false" aria-controls="sbAdmin">
                                    <span><i class="fas fa-tools me-2"></i>Administração</span>
                                    <i class="fas fa-chevron-down small"></i>
                                </a>
                                <div class="collapse {{ request()->routeIs('admin.*') ? 'show' : '' }}" id="sbAdmin">
                                    <ul class="nav flex-column ms-3 my-2">
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                                                href="{{ route('admin.users.index') }}" title="Gestão de Usuários"><i
                                                    class="fas fa-users-cog me-2"></i>Gestão de Usuários</a></li>
                                    </ul>
                                </div>
                            </li>
                        @endif

                        <div class="nav-section">Suporte</div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}"
                                href="{{ route('feedbacks.create') }}" title="Feedback">
                                <i class="fas fa-comment me-2"></i>Feedback
                            </a>
                        </li>
                    @endauth
                </ul>
            </nav>
            @auth
                <div class="px-3 pb-3 mt-auto user-profile-section">
                    <div class="d-flex gap-2 mb-2">
                        <button class="btn btn-sm btn-outline-light" data-toggle="dark-mode" title="Modo escuro"
                            type="button"><i class="fas fa-moon"></i></button>
                        <button class="btn btn-sm btn-outline-light" data-toggle="high-contrast"
                            title="Contraste elevado" type="button"><i class="fas fa-adjust"></i></button>
                    </div>
                    <div class="dropdown">
                        <a class="text-white text-decoration-none dropdown-toggle" href="#"
                            id="userSidebarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Meu Perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Sair</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            @endauth
        </aside>

        <!-- Content Column (main + footer beside sidebar) -->
        <div class="content-area flex-grow-1 d-flex flex-column">
            <div class="topbar py-2">
                <div class="container d-none d-lg-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary" id="toggleSidebar" title="Alternar menu"
                        aria-controls="desktopSidebar" aria-expanded="true">
                        <i class="fas fa-bars"></i>
                    </button>
                    <form id="globalSearchForm" class="flex-grow-1 position-relative">
                        <div class="input-group topbar-search">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="search" name="q" class="form-control search"
                                placeholder="Pesquisar (Viaturas, Requisições, Documentos)..." aria-label="Pesquisar"
                                autocomplete="off">
                        </div>
                        <div id="searchResults" class="dropdown-menu w-100 shadow mt-1"
                            style="display:none; max-height: 400px; overflow-y: auto;"></div>
                    </form>
                    <div class="dropdown quick-actions">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bolt me-1"></i>Atalhos
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="{{ route('requisicoes.produtos.create.novo') }}"><i
                                        class="fas fa-plus-circle me-2"></i>Nova Requisição</a></li>
                            <li><a class="dropdown-item" href="{{ route('requisicoes.passagem.create.novo') }}"><i
                                        class="fas fa-ticket-alt me-2"></i>Nova Requisição de Passagem</a></li>
                            <li><a class="dropdown-item" href="{{ route('reservas.create') }}"><i
                                        class="fas fa-calendar-plus me-2"></i>Novo Agendamento</a></li>
                            @can('create', App\Models\DocumentoEntrada::class)
                                <li><a class="dropdown-item" href="{{ route('documentos-entradas.create') }}"><i
                                            class="fas fa-plus-circle me-2"></i>Nova Entrada de Documento</a></li>
                            @endcan
                            <li><a class="dropdown-item" href="{{ route('termos.index') }}"><i
                                        class="fas fa-file-alt me-2"></i>Termos de Entrega</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="{{ route('feedbacks.create') }}"><i
                                        class="fas fa-comment me-2"></i>Enviar Feedback</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-outline-secondary" id="toggleTheme" title="Alternar tema">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="btn btn-outline-secondary" id="toggleContrast" title="Alternar contraste">
                        <i class="fas fa-adjust"></i>
                    </button>
                    @auth
                        <div>
                            @include('components.notifications-dropdown')
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>{{ Auth::user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i
                                            class="fas fa-user-cog me-2"></i>Perfil</a></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item" type="submit"><i
                                                class="fas fa-sign-out-alt me-2"></i>Sair</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endauth
                    @guest
                        <a class="btn btn-outline-primary" href="{{ route('login') }}">Entrar</a>
                    @endguest
                </div>
            </div>
            <!-- Mobile top bar -->
            <div class="d-lg-none bg-primary text-white p-2 sticky-top shadow-sm w-100">
                <div class="d-flex justify-content-between align-items-center">
                    <a class="text-white text-decoration-none fw-semibold" href="{{ route('home') }}"><i
                            class="fas fa-truck-moving me-2"></i>GPN-AGIL</a>
                    <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile offcanvas -->
            <div class="offcanvas offcanvas-start text-bg-primary" tabindex="-1" id="mobileSidebar"
                aria-labelledby="mobileSidebarLabel" data-bs-scroll="true">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="mobileSidebarLabel"><i
                            class="fas fa-truck-moving me-2"></i>GPN-AGIL</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column">
                    @auth
                        <ul class="nav nav-pills flex-column gap-1 mb-3">
                            <div class="nav-section">Operações</div>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                                    href="{{ route('home') }}"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                            </li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('viaturas.*') ? 'active' : '' }}"
                                    href="{{ route('viaturas.index') }}"><i class="fas fa-car me-2"></i>Viaturas</a></li>
                            @can('viewAny', App\Models\Requisicao::class)
                                <li class="nav-item"><a
                                        class="nav-link {{ request()->routeIs('requisicoes.index') ? 'active' : '' }}"
                                        href="{{ route('requisicoes.index') }}"><i
                                            class="fas fa-list me-2"></i>Requisições</a>
                                    @if (
                                        !empty($menuCounts) &&
                                            ($menuCounts['can_review_requisicoes'] ?? false) &&
                                            ($menuCounts['pend_requisicoes'] ?? 0) > 0)
                                        <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                                class="fas fa-clock me-1"></i>{{ $menuCounts['pend_requisicoes'] }}</span>
                                    @endif
                                </li>
                            @endcan
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('requisicoes.produtos.index') ? 'active' : '' }}"
                                    href="{{ route('requisicoes.produtos.index') }}"><i
                                        class="fas fa-box-open me-2"></i>Produtos</a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('requisicoes.oficina.index') ? 'active' : '' }}"
                                    href="{{ route('requisicoes.oficina.index') }}"><i
                                        class="fas fa-wrench me-2"></i>Oficina</a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('requisicoes.servico.index') ? 'active' : '' }}"
                                    href="{{ route('requisicoes.servico.index') }}"><i
                                        class="fas fa-briefcase me-2"></i>Serviços</a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('requisicoes.passagem.index') ? 'active' : '' }}"
                                    href="{{ route('requisicoes.passagem.index') }}"><i
                                        class="fas fa-ticket-alt me-2"></i>Passagem</a></li>
                            <div class="nav-section">Documentos</div>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') !== 'pendentes_recebimento' ? 'active' : '' }}"
                                    href="{{ route('documentos-entradas.index') }}"><i
                                        class="fas fa-inbox me-2"></i>Entradas</a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'pendentes_recebimento' ? 'active' : '' }}"
                                    href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento']) }}"><i
                                        class="fas fa-hourglass-half me-2"></i>Por Receber
                                    @if (!empty($menuCounts) && ($menuCounts['pend_documentos_por_receber'] ?? 0) > 0)
                                        <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                                class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_por_receber'] }}</span>
                                    @endif
                                </a></li>
                            <li class="nav-item">
                                <a class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado']) ? 'active' : '' }}"
                                    data-bs-toggle="collapse" href="#sbVistosDept2" role="button" aria-expanded="false"
                                    aria-controls="sbVistosDept2">
                                    <span><i class="fas fa-eye me-2"></i>Vistos Departamento</span>
                                    <i class="fas fa-chevron-down small"></i>
                                </a>
                                <div class="collapse {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado']) ? 'show' : '' }}"
                                    id="sbVistosDept2">
                                    <ul class="nav flex-column ms-3 my-2">
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_pendente' ? 'active' : '' }}"
                                                href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}">Pendente
                                                @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                                                    <span
                                                        class="badge rounded-pill ms-auto">{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                                                @endif
                                            </a></li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_aprovado' ? 'active' : '' }}"
                                                href="{{ route('documentos-entradas.index', ['meus' => 'visto_aprovado']) }}">Aprovado
                                                @if (!empty($menuCounts) && ($menuCounts['aprov_documentos_visto_departamento'] ?? 0) > 0)
                                                    <span
                                                        class="badge rounded-pill ms-auto">{{ $menuCounts['aprov_documentos_visto_departamento'] }}</span>
                                                @endif
                                            </a></li>
                                        <li class="nav-item"><a
                                                class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_rejeitado' ? 'active' : '' }}"
                                                href="{{ route('documentos-entradas.index', ['meus' => 'visto_rejeitado']) }}">Rejeitado
                                                @if (!empty($menuCounts) && ($menuCounts['rej_documentos_visto_departamento'] ?? 0) > 0)
                                                    <span
                                                        class="badge rounded-pill ms-auto">{{ $menuCounts['rej_documentos_visto_departamento'] }}</span>
                                                @endif
                                            </a></li>
                                    </ul>
                                </div>
                            </li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_pendente' ? 'active' : '' }}"
                                    href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}"><i
                                        class="fas fa-eye me-2"></i>Visto Departamento Pendente
                                    @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                                        <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                                class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                                    @endif
                                </a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') === 'visto_gabinete_pendente' ? 'active' : '' }}"
                                    href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente']) }}"><i
                                        class="fas fa-user-tie me-2"></i>Visto Gabinete Pendente
                                    @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_gabinete'] ?? 0) > 0)
                                        <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                                class="fas fa-clock me-1"></i>{{ $menuCounts['pend_documentos_visto_gabinete'] }}</span>
                                    @endif
                                </a></li>
                            @can('create', App\Models\DocumentoEntrada::class)
                                <li class="nav-item"><a
                                        class="nav-link {{ request()->routeIs('documentos-entradas.create') ? 'active' : '' }}"
                                        href="{{ route('documentos-entradas.create') }}"><i
                                            class="fas fa-plus-circle me-2"></i>Nova Entrada</a></li>
                            @endcan
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('termos.*') ? 'active' : '' }}"
                                    href="{{ route('termos.index') }}"><i
                                        class="fas fa-file-signature me-2"></i>Termos</a></li>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('credenciais.*') ? 'active' : '' }}"
                                    href="{{ route('credenciais.index') }}"><i
                                        class="fas fa-id-card me-2"></i>Credenciais</a></li>
                            @can('viewAny', App\Models\ReservaEspaco::class)
                                <li class="nav-item"><a
                                        class="nav-link {{ request()->routeIs('reservas.index') ? 'active' : '' }}"
                                        href="{{ route('reservas.index') }}"><i
                                            class="fas fa-calendar-alt me-2"></i>Reservas</a>
                                    @if (!empty($menuCounts) && ($menuCounts['can_review_reservas'] ?? false) && ($menuCounts['pend_reservas'] ?? 0) > 0)
                                        <span class="badge rounded-pill bg-warning text-dark ms-2"><i
                                                class="fas fa-clock me-1"></i>{{ $menuCounts['pend_reservas'] }}</span>
                                    @endif
                                </li>
                                <li class="nav-item"><a
                                        class="nav-link {{ request()->routeIs('reservas.calendar') ? 'active' : '' }}"
                                        href="{{ route('reservas.calendar') }}"><i
                                            class="fas fa-calendar-day me-2"></i>Calendário</a></li>
                            @endcan
                            <div class="nav-section">Estrutura</div>
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('empresas.*') ? 'active' : '' }}"
                                    href="{{ route('empresas.index') }}"><i
                                        class="fas fa-building me-2"></i>Empresas</a>
                            </li>
                            @if (Auth::user()->role && Auth::user()->role->name === 'admin')
                                <li class="nav-item"><a
                                        class="nav-link {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}"
                                        href="{{ route('gabinetes.index') }}"><i
                                            class="fas fa-building-columns me-2"></i>Gabinetes</a></li>
                            @endif
                            <li class="nav-item"><a
                                    class="nav-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}"
                                    href="{{ route('departamentos.index') }}"><i
                                        class="fas fa-sitemap me-2"></i>Departamentos</a></li>
                            @if (Auth::user()->role && Auth::user()->role->name === 'admin')
                                <div class="nav-section">Administração</div>
                                <li class="nav-item"><a
                                        class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('admin.*') ? 'active' : '' }}"
                                        data-bs-toggle="collapse" href="#sbAdmin" role="button" aria-expanded="false"
                                        aria-controls="sbAdmin">
                                        <span><i class="fas fa-tools me-2"></i>Administração</span>
                                        <i class="fas fa-chevron-down small"></i>
                                    </a>
                                    <div class="collapse {{ request()->routeIs('admin.*') ? 'show' : '' }}"
                                        id="sbAdmin">
                                        <ul class="nav flex-column ms-3 my-2">
                                            <li class="nav-item"><a
                                                    class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                                                    href="{{ route('admin.users.index') }}"><i
                                                        class="fas fa-users-cog me-2"></i>Gestão de Usuários</a></li>
                                        </ul>
                                    </div>
                                </li>
                            @endif

                            <div class="nav-section">Suporte</div>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}"
                                    href="{{ route('feedbacks.create') }}"><i
                                        class="fas fa-comment me-2"></i>Feedback</a>
                            </li>
                            <div class="nav-section">Atalhos</div>
                            @can('create', App\Models\Requisicao::class)
                                <li class="nav-item"><a class="nav-link"
                                        href="{{ route('requisicoes.produtos.create.novo') }}"><i
                                            class="fas fa-plus-circle me-2"></i>Nova Requisição</a></li>
                                <li class="nav-item"><a class="nav-link"
                                        href="{{ route('requisicoes.passagem.create.novo') }}"><i
                                            class="fas fa-ticket-alt me-2"></i>Nova Requisição de Passagem</a></li>
                            @endcan
                            @can('create', App\Models\ReservaEspaco::class)
                                <li class="nav-item"><a class="nav-link" href="{{ route('reservas.create') }}"><i
                                            class="fas fa-calendar-plus me-2"></i>Novo Agendamento</a></li>
                            @endcan
                            @can('create', App\Models\DocumentoEntrada::class)
                                <li class="nav-item"><a class="nav-link"
                                        href="{{ route('documentos-entradas.create') }}"><i
                                            class="fas fa-plus-circle me-2"></i>Nova Entrada de Documento</a></li>
                            @endcan
                            <li class="nav-item"><a class="nav-link" href="{{ route('termos.index') }}"><i
                                        class="fas fa-file-alt me-2"></i>Termos de Entrega</a></li>
                        </ul>

                        <div class="mt-auto">
                            <div class="d-flex gap-2 mb-2">
                                <button class="btn btn-sm btn-outline-light" data-toggle="dark-mode" title="Modo escuro"
                                    type="button"><i class="fas fa-moon"></i></button>
                                <button class="btn btn-sm btn-outline-light" data-toggle="high-contrast"
                                    title="Contraste elevado" type="button"><i class="fas fa-adjust"></i></button>
                            </div>
                            <div class="dropdown">
                                <a class="text-white text-decoration-none dropdown-toggle" href="#"
                                    id="userMobileDropdown" role="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Meu Perfil</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Sair</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @endauth
                    @guest
                        <a class="btn btn-light" href="{{ route('login') }}">Login</a>
                    @endguest
                </div>
            </div>

            <!-- Main Content -->
            <main id="mainContent" class="flex-grow-1 py-4">
                <div class="container">
                    @yield('breadcrumbs')
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    @endif

                    @if (session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            <!-- Footer -->
            <footer class="sticky-footer bg-white mt-auto border-top">
                <div class="container my-auto">
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-2">
                        <div class="copyright text-center my-auto">
                            <span>&copy; {{ date('Y') }} GPN-AGIL</span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
    <!-- Pusher & Laravel Echo (CDNs) -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

    <script>
        // NProgress Init
        document.addEventListener('DOMContentLoaded', () => {
            NProgress.configure({
                showSpinner: false
            });
        });
        window.addEventListener('beforeunload', () => {
            NProgress.start();
        });
        $(document).ajaxStart(() => NProgress.start());
        $(document).ajaxStop(() => NProgress.done());

        // Persist theme and contrast preferences across sessions
        (function initTheme() {
            const root = document.documentElement;
            const storedTheme = localStorage.getItem('gpn-theme');
            const storedContrast = localStorage.getItem('gpn-contrast');
            if (storedTheme) {
                root.setAttribute('data-bs-theme', storedTheme);
            }
            if (storedContrast === 'high') {
                root.classList.add('high-contrast');
            }
            const btnTheme = document.getElementById('toggleTheme');
            const btnContrast = document.getElementById('toggleContrast');
            if (btnTheme) {
                btnTheme.addEventListener('click', () => {
                    const current = root.getAttribute('data-bs-theme') || 'light';
                    const next = current === 'dark' ? 'light' : 'dark';
                    root.setAttribute('data-bs-theme', next);
                    localStorage.setItem('gpn-theme', next);
                });
            }
            if (btnContrast) {
                btnContrast.addEventListener('click', () => {
                    const isHigh = root.classList.toggle('high-contrast');
                    localStorage.setItem('gpn-contrast', isHigh ? 'high' : 'normal');
                });
            }
        })();

        // Global Search: AJAX Search
        (function initGlobalSearch() {
            const form = document.getElementById('globalSearchForm');
            if (!form) return;
            const input = form.querySelector('input[name="q"]');
            const results = document.getElementById('searchResults');
            let timeout = null;

            input.addEventListener('input', function() {
                clearTimeout(timeout);
                const q = this.value.trim();
                if (q.length < 2) {
                    results.style.display = 'none';
                    return;
                }

                timeout = setTimeout(() => {
                    fetch('{{ route('global.search') }}?q=' + encodeURIComponent(q))
                        .then(res => res.json())
                        .then(data => {
                            if (data.length === 0) {
                                results.innerHTML =
                                    '<div class="dropdown-item disabled text-muted">Nenhum resultado encontrado</div>';
                            } else {
                                results.innerHTML = '';
                                let currentCategory = '';
                                data.forEach(item => {
                                    if (item.category !== currentCategory) {
                                        if (currentCategory !== '') results.innerHTML +=
                                            '<div class="dropdown-divider"></div>';
                                        results.innerHTML +=
                                            `<div class="dropdown-header text-uppercase small fw-bold mt-1">${item.category}</div>`;
                                        currentCategory = item.category;
                                    }
                                    results.innerHTML += `
                                        <a href="${item.url}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                            <div class="bg-light p-2 rounded"><i class="fas ${item.icon} text-primary"></i></div>
                                            <div class="text-truncate">${item.label}</div>
                                        </a>
                                    `;
                                });
                            }
                            results.style.display = 'block';
                        })
                        .catch(err => {
                            console.error(err);
                            results.style.display = 'none';
                        });
                }, 300);
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!form.contains(e.target)) {
                    results.style.display = 'none';
                }
            });

            // Prevent default submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        })();
        (function initSidebarToggle() {
            const btn = document.getElementById('toggleSidebar');
            const body = document.body;
            const KEY = 'gpn-sidebar';

            function applyState(state) {
                body.classList.remove('sidebar-collapsed', 'sidebar-hidden');
                if (state === 'collapsed') {
                    body.classList.add('sidebar-collapsed');
                    btn?.setAttribute('aria-expanded', 'false');
                } else if (state === 'hidden') {
                    body.classList.add('sidebar-hidden');
                    btn?.setAttribute('aria-expanded', 'false');
                } else {
                    btn?.setAttribute('aria-expanded', 'true');
                }
            }
            const saved = localStorage.getItem(KEY) || 'expanded';
            applyState(saved);
            btn?.addEventListener('click', function() {
                const w = window.innerWidth || document.documentElement.clientWidth;
                if (w < 992) {
                    const mob = document.getElementById('mobileSidebar');
                    if (mob) {
                        try {
                            (bootstrap.Offcanvas.getInstance(mob) || new bootstrap.Offcanvas(mob)).show();
                        } catch (_) {}
                    }
                    return;
                }
                const current = localStorage.getItem(KEY) || 'expanded';
                const next = current === 'expanded' ? 'collapsed' : (current === 'collapsed' ? 'hidden' :
                    'expanded');
                applyState(next);
                localStorage.setItem(KEY, next);
            });
        })();
        // CSRF para AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        // Utilitários UI de notificações
        function renderNotifications(list) {
            const $list = $('#notificationsList');
            $list.empty();
            if (!list || !list.length) {
                $list.append('<div class="p-3 text-center text-muted">Sem notificações.</div>');
                return;
            }
            list.forEach(function(n) {
                const url = n.url || '#';
                const unreadClass = n.read_at ? '' : 'fw-semibold';
                const dotColor = n.read_at ? 'transparent' : '#0d6efd';
                const createdAt = (new Date(n.created_at)).toLocaleString('pt-BR');
                const item = `
                    <a href="${url}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item ${unreadClass}"
                       data-id="${n.id}" data-url="${url}">
                        <i class="fas fa-circle mt-1" style="font-size:.5rem;color:${dotColor}"></i>
                        <div class="flex-grow-1">
                            <div class="small text-muted">${createdAt}</div>
                            <div class="text-wrap">${n.title || 'Nova notificação'}</div>
                        </div>
                    </a>`;
                $list.append(item);
            });
        }

        function updateBadge(count) {
            const $badge = $('#notificationsBadge');
            $badge.text(count);
        }

        async function fetchNotifications() {
            try {
                const res = await $.getJSON("{{ route('notifications.index') }}");
                updateBadge(res.unread_count || 0);
                renderNotifications(res.notifications || []);
            } catch (e) {
                // silencioso
            }
        }

        // Eventos da dropdown
        $(document).on('show.bs.dropdown', '#notificationsDropdown', fetchNotifications);

        // Clique em item: marca como lida e segue link
        $(document).on('click', '.notification-item', async function(e) {
            const id = $(this).data('id');
            const url = $(this).data('url');
            try {
                await $.post("{{ url('/notifications/read') }}/" + id);
                const current = parseInt($('#notificationsBadge').text() || '0', 10);
                if (current > 0) updateBadge(current - 1);
            } catch (err) {
                /* ignore */
            }
            if (!url || url === '#') e.preventDefault();
        });

        // Marcar todas
        $(document).on('click', '#markAllReadBtn', async function() {
            try {
                await $.post("{{ route('notifications.read_all') }}");
                updateBadge(0);
                $('#notificationsList .notification-item').removeClass('fw-semibold')
                    .find('.fa-circle').css('color', 'transparent');
            } catch (err) {
                /* ignore */
            }
        });

        // Echo / Pusher/Ably: notificações em tempo real + toast
        (function initEcho() {
            const DRIVER = "{{ config('broadcasting.default') }}";
            const PUSHER_KEY = "{{ config('broadcasting.connections.pusher.key') }}";
            const PUSHER_CLUSTER =
                "{{ config('broadcasting.connections.pusher.options.cluster') ?? config('broadcasting.connections.pusher.cluster') }}";
            const ABLY_KEY = "{{ config('broadcasting.connections.ably.key') }}";
            const USER_ID = {{ Auth::id() ?? 'null' }};
            if (!USER_ID) return;

            let key = null;
            let echoOptions = {
                broadcaster: 'pusher',
                forceTLS: true,
                authEndpoint: '{{ url('/broadcasting/auth') }}',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                }
            };

            if (DRIVER === 'pusher' && PUSHER_KEY) {
                key = PUSHER_KEY;
                echoOptions.key = PUSHER_KEY;
                echoOptions.cluster = PUSHER_CLUSTER || 'mt1';
            } else if (DRIVER === 'ably' && ABLY_KEY) {
                key = ABLY_KEY;
                echoOptions.key = ABLY_KEY;
                echoOptions.wsHost = 'realtime.ably.io';
                echoOptions.wsPort = 443;
                echoOptions.wssPort = 443;
                echoOptions.disableStats = true;
                echoOptions.enabledTransports = ['ws', 'wss'];
            }

            if (!key) return;
            window.Pusher = Pusher;
            window.Echo = new Echo(echoOptions);
            window.__echoActive = true;

            function handleIncomingNotification(n) {
                const title = n.title || n.message || n.assunto || n.numero || 'Nova notificação';
                const url = n.url || '#';
                // Atualiza badge
                const current = parseInt($('#notificationsBadge').text() || '0', 10);
                updateBadge(current + 1);
                // Prepend na lista
                const item =
                    `
                    <a href="${url}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item fw-semibold" data-id="${n.id || ''}" data-url="${url}">
                        <i class=\"fas fa-circle mt-1\" style=\"font-size:.5rem;color:#0d6efd\"></i>
                        <div class=\"flex-grow-1\">\n                            <div class=\"small text-muted\">agora mesmo</div>\n                            <div class=\"text-wrap\">${title}</div>\n                        </div>\n                    </a>`;
                $('#notificationsList').prepend(item);
                var isDoc = (n.acao === 'encaminhado_externo' || n.acao === 'encaminhado_interno');
                showRealtimeToast(title, {
                    variant: isDoc ? 'text-bg-primary' : 'text-bg-info',
                    icon: isDoc ? 'fa-file-import' : 'fa-bell'
                });
            }
            window.Echo.private('App.Models.User.' + USER_ID)
                .notification(function(notification) {
                    handleIncomingNotification(notification);
                });
        })();

        function showRealtimeToast(text, opts) {
            var container = document.getElementById('toastRealtimeArea');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.id = 'toastRealtimeArea';
                document.body.appendChild(container);
            }
            var MAX = 3;
            window.__toastActiveCount = window.__toastActiveCount || 0;
            window.__toastPending = window.__toastPending || [];
            var variantClass = (opts && opts.variant) ? opts.variant : 'text-bg-info';
            var iconClass = (opts && opts.icon) ? opts.icon : 'fa-bell';

            function createToast(txt) {
                const html = `
                    <div class="toast align-items-center ${variantClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body"><i class="fas ${iconClass} me-2"></i>${txt}</div>
                            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
                        </div>
                    </div>`;
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                return wrap.firstChild;
            }

            function launch(txt, o) {
                const el = createToast(txt);
                container.appendChild(el);
                var t = new bootstrap.Toast(el, {
                    delay: 3500,
                    autohide: true
                });
                t.show();
                el.addEventListener('hidden.bs.toast', function() {
                    window.__toastActiveCount = Math.max(0, (window.__toastActiveCount || 1) - 1);
                    if (window.__toastPending && window.__toastPending.length) {
                        const next = window.__toastPending.shift();
                        window.__toastActiveCount++;
                        launch(next.text, next.opts);
                    }
                });
            }
            if (window.__toastActiveCount >= MAX) {
                window.__toastPending.push({
                    text,
                    opts
                });
            } else {
                window.__toastActiveCount++;
                launch(text, opts);
            }
        }

        // Web Push (frontend pronto)
        (function initWebPush() {
            const VAPID_PUBLIC_KEY = "{{ env('VAPID_PUBLIC_KEY') }}";
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID_PUBLIC_KEY) return;
            navigator.serviceWorker.register('/sw.js').then(async function(reg) {
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') return;
                let sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                    });
                }
                await fetch("{{ route('push.subscribe') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content')
                    },
                    body: JSON.stringify(sub)
                });
            }).catch(function() {
                /* ignore */
            });

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }
        })();

        (function cleanupBackdrops() {
            function run() {
                var w = window.innerWidth || document.documentElement.clientWidth;
                if (w >= 992) {
                    document.querySelectorAll('.offcanvas-backdrop, .modal-backdrop').forEach(function(el) {
                        try {
                            el.remove();
                        } catch (_) {}
                    });
                    document.body.classList.remove('offcanvas-open', 'modal-open');
                    var mob = document.getElementById('mobileSidebar');
                    if (mob && mob.classList.contains('show')) {
                        try {
                            var oc = bootstrap.Offcanvas.getInstance(mob) || new bootstrap.Offcanvas(mob);
                            oc.hide();
                        } catch (_) {
                            mob.classList.remove('show');
                        }
                    }
                }
                var overlay = document.getElementById('lightboxOverlay');
                if (overlay && !document.querySelector('[data-lightbox]')) {
                    overlay.style.display = 'none';
                }
            }
            document.addEventListener('DOMContentLoaded', run);
            window.addEventListener('resize', run);
        })();

        // Fallback de polling para novos itens de notificações
        (function initNotificationsPolling() {
            let lastSeenIds = new Set();
            let initialized = false;
            const POLL_MS = 5000;
            async function poll() {
                try {
                    const res = await fetch("{{ route('notifications.index') }}", {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json();
                    const items = json.notifications || [];
                    if (!initialized) {
                        items.forEach(it => lastSeenIds.add(it.id));
                        initialized = true;
                        updateBadge(json.unread_count || 0);
                        return;
                    }
                    // Detecta novos itens
                    let newItems = [];
                    items.forEach(it => {
                        if (it.id && !lastSeenIds.has(it.id)) {
                            newItems.push(it);
                            lastSeenIds.add(it.id);
                        }
                    });
                    if (newItems.length) {
                        updateBadge((json.unread_count || 0));
                        newItems.forEach(n => {
                            const title = n.title || 'Nova notificação';
                            var isDoc = (n.acao === 'encaminhado_externo' || n.acao ===
                                'encaminhado_interno');
                            showRealtimeToast(title, {
                                variant: isDoc ? 'text-bg-primary' : 'text-bg-info',
                                icon: isDoc ? 'fa-file-import' : 'fa-bell'
                            });
                        });
                    }
                } catch (e) {
                    /* ignore */
                }
            }
            // Se Echo não estiver ativo, habilita polling
            if (!window.__echoActive) {
                setInterval(poll, POLL_MS);
                // primeira carga
                poll();
            }
        })();

        (function observeBackdrops() {
            try {
                var obs = new MutationObserver(function() {
                    var w = window.innerWidth || document.documentElement.clientWidth;
                    if (w >= 992) {
                        document.querySelectorAll('.offcanvas-backdrop, .modal-backdrop').forEach(function(el) {
                            try {
                                el.remove();
                            } catch (_) {}
                        });
                        document.body.classList.remove('offcanvas-open', 'modal-open');
                        var mob = document.getElementById('mobileSidebar');
                        if (mob && mob.classList.contains('show')) {
                            try {
                                var oc = bootstrap.Offcanvas.getInstance(mob) || new bootstrap.Offcanvas(mob);
                                oc.hide();
                            } catch (_) {
                                mob.classList.remove('show');
                            }
                        }
                    }
                    var overlay = document.getElementById('lightboxOverlay');
                    if (overlay && !document.querySelector('[data-lightbox]')) {
                        overlay.style.display = 'none';
                    }
                });
                obs.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } catch (_) {}
        })();
    </script>

    <script>
        (function() {
            function initDT() {
                var els = document.querySelectorAll('table[data-dt="true"]');
                els.forEach(function(t) {
                    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;
                    var $t = jQuery(t);
                    if ($t.hasClass('dataTable')) return;
                    var cfg = {
                        paging: (t.dataset.dtPaging === 'true'),
                        info: false,
                        responsive: true,
                        ordering: (t.dataset.dtOrdering !== 'false'),
                        searching: (t.dataset.dtSearching !== 'false'),
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                        }
                    };
                    $t.DataTable(cfg);
                });
            }
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                initDT();
            }
            document.addEventListener('DOMContentLoaded', initDT);
        })();
    </script>

    @stack('lightbox_scripts')

    <script>
        (function initTooltips() {
            var list = [].slice.call(document.querySelectorAll(
                '[data-bs-toggle="tooltip"], .sidebar .nav-link[title]'));
            list.forEach(function(el) {
                try {
                    new bootstrap.Tooltip(el, {
                        placement: 'right'
                    });
                } catch (_) {}
            });
        })();

        (function initSearchShortcut() {
            document.addEventListener('keydown', function(e) {
                var key = e.key || '';
                if ((e.ctrlKey || e.metaKey) && key.toLowerCase() === 'k') {
                    e.preventDefault();
                    var input = document.querySelector('.topbar .search');
                    if (input) {
                        input.focus();
                        try {
                            input.select();
                        } catch (_) {}
                    }
                }
            });
        })();
    </script>

    <!-- Toasts de sessão -->
    @if (session('success') || session('error') || session('warning') || $errors->any())
        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastArea" aria-live="polite"
            aria-atomic="true">
            @if (session('success'))
                <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive"
                    aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                            aria-label="Fechar"></button>
                    </div>
                </div>
            @endif
            @if (session('error'))
                <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive"
                    aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body"><i class="fas fa-times-circle me-2"></i>{{ session('error') }}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                            aria-label="Fechar"></button>
                    </div>
                </div>
            @endif
            @if (session('warning'))
                <div class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive"
                    aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body text-dark"><i
                                class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}</div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"
                            aria-label="Fechar"></button>
                    </div>
                </div>
            @endif
            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <div class="toast align-items-center text-bg-danger border-0" role="alert"
                        aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body"><i class="fas fa-times-circle me-2"></i>{{ $error }}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast" aria-label="Fechar"></button>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
        <script>
            (function() {
                var container = document.getElementById('toastArea');
                if (container) {
                    container.querySelectorAll('.toast').forEach(function(el) {
                        var t = new bootstrap.Toast(el, {
                            delay: 5000,
                            autohide: true
                        });
                        t.show();
                        try {
                            if (el.classList.contains('text-bg-success')) {
                                var body = el.querySelector('.toast-body');
                                var text = body ? body.textContent.trim() : 'Operação concluída';
                                if (typeof showRealtimeToast === 'function' && text) {
                                    showRealtimeToast(text);
                                }
                            }
                        } catch (_) {}
                    });
                }
            })();
        </script>
    @endif

    @yield('scripts')
    <script>
        // Toasts System
        (function initToasts() {
            const toastContainer = document.querySelector('.toast-container');

            window.showToast = function(message, type = 'success') {
                const bgClass = type === 'success' ? 'text-bg-success' :
                    (type === 'error' ? 'text-bg-danger' :
                        (type === 'warning' ? 'text-bg-warning' : 'text-bg-primary'));

                const id = 'toast-' + Date.now();
                const html = `
                    <div id="${id}" class="toast align-items-center ${bgClass} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;

                toastContainer.insertAdjacentHTML('beforeend', html);
                const toastEl = document.getElementById(id);
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000
                });
                toast.show();

                toastEl.addEventListener('hidden.bs.toast', () => {
                    toastEl.remove();
                });
            };

            // Auto-show flash messages
            @if (session('success'))
                showToast("{{ session('success') }}", 'success');
            @endif
            @if (session('error'))
                showToast("{{ session('error') }}", 'error');
            @endif
            @if (session('warning'))
                showToast("{{ session('warning') }}", 'warning');
            @endif
            @if (session('info'))
                showToast("{{ session('info') }}", 'info');
            @endif
        })();
    </script>
</body>

</html>
