{{--
    Navegação do menu lateral / offcanvas (simplificada 1-clique).
--}}
@php
    $p = $idPrefix ?? '';
@endphp

@auth
    @php
        $defaultTab = $menuCounts['default_tab'] ?? 'atribuidos_mim';
        $extCount = $menuCounts['ext_pendentes'] ?? 0;
        $intCount = $menuCounts['int_pendentes'] ?? 0;

        $isAdmin = Auth::user()->isAdmin();
        $canAdmin = $isAdmin || Auth::user()->canAny(['configuracoes.editar', 'usuarios.gerir', 'departamentos.gerir', 'permissoes.gerir']);
        $canManageTemplates = $isAdmin || Auth::user()->hasRole('admin') || (method_exists(Auth::user(), 'isChefeGabinete') && Auth::user()->isChefeGabinete()) || (method_exists(Auth::user(), 'isSuperChefeGabinete') && Auth::user()->isSuperChefeGabinete());
    @endphp

    {{-- 1. INÍCIO --}}
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" title="Dashboard">
        <i class="fas fa-th-large nav-icon"></i>
        <span>Início</span>
    </a>

    {{-- 2. DOCUMENTOS EXTERNOS --}}
    <a href="{{ route('documentos-entradas.index', ['tab' => $defaultTab]) }}"
        class="nav-link {{ request()->routeIs('documentos-entradas.*') || request()->routeIs('tarefas.*') ? 'active' : '' }}"
        title="Documentos Externos (Entradas)">
        <i class="fas fa-inbox nav-icon"></i>
        <span>Documentos Externos</span>
        @if ($extCount > 0)
            <span class="badge-nav badge-warning ms-auto">{{ $extCount }}</span>
        @endif
    </a>

    {{-- 3. DOCUMENTOS INTERNOS --}}
    <a href="{{ route('documentos-internos.index') }}"
        class="nav-link {{ request()->routeIs('documentos-internos.*') ? 'active' : '' }}"
        title="Documentos Internos">
        <i class="fas fa-file-alt nav-icon"></i>
        <span>Documentos Internos</span>
        @if ($intCount > 0)
            <span class="badge-nav badge-info ms-auto">{{ $intCount }}</span>
        @endif
    </a>

    {{-- TEMPLATES DE DOCUMENTOS (APENAS ADMIN E CHEFE DE GABINETE) --}}
    @if ($canManageTemplates)
        <a href="{{ route('modelos.index') }}"
            class="nav-link {{ request()->routeIs('modelos.*') ? 'active' : '' }}"
            title="Templates e Modelos de Documentos">
            <i class="fas fa-file-contract nav-icon"></i>
            <span>Templates de Documentos</span>
        </a>
    @endif

    {{-- 4. ARQUIVO DIGITAL --}}
    <a href="{{ route('edms.index') }}"
        class="nav-link {{ request()->routeIs('edms.*') || request()->routeIs('pastas.*') ? 'active' : '' }}"
        title="Arquivo Digital e Gestão de Pastas">
        <i class="fas fa-archive nav-icon"></i>
        <span>Arquivo Digital</span>
    </a>

    {{-- 5. SERVIÇOS & MEIOS (DROPDOWN / COLLAPSIBLE) --}}
    @php
        $servicosActive = request()->routeIs('viaturas.*') || request()->routeIs('credenciais.*');
    @endphp
    <hr class="sidebar-divider">
    <div class="nav-item-wrapper">
        <a href="#{{ $p }}menuServicos" data-bs-toggle="collapse" title="Gestão de Viaturas e Credenciais"
            class="nav-link {{ $servicosActive ? 'active' : '' }}"
            aria-expanded="{{ $servicosActive ? 'true' : 'false' }}"
            aria-controls="{{ $p }}menuServicos">
            <i class="fas fa-cubes nav-icon"></i>
            <span>Serviços & Frota</span>
            <i class="fas fa-chevron-down nav-arrow"></i>
        </a>
        <div class="collapse {{ $servicosActive ? 'show' : '' }}" id="{{ $p }}menuServicos">
            <div class="submenu">
                <a href="{{ route('viaturas.index') }}"
                    class="nav-link {{ request()->routeIs('viaturas.*') ? 'active' : '' }}">
                    <i class="fas fa-car me-2"></i>Gestão de Viaturas
                </a>
                <a href="{{ route('credenciais.index') }}"
                    class="nav-link {{ request()->routeIs('credenciais.*') ? 'active' : '' }}">
                    <i class="fas fa-id-card me-2"></i>Gestão de Credenciais
                </a>
            </div>
        </div>
    </div>

    {{-- 6. ADMINISTRAÇÃO (Apenas para Admins/Gestores) --}}
    @if ($canAdmin)
        <hr class="sidebar-divider">
        <div class="nav-group-label">Administração</div>

        <div class="nav-item-wrapper">
            <a href="#{{ $p }}menuConfig" data-bs-toggle="collapse" title="Administração do Sistema"
                class="nav-link {{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') || request()->routeIs('configuracoes.*') ? 'active' : '' }}"
                aria-expanded="{{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') || request()->routeIs('configuracoes.*') ? 'true' : 'false' }}"
                aria-controls="{{ $p }}menuConfig">
                <i class="fas fa-sliders nav-icon"></i>
                <span>Gestão do Sistema</span>
                <i class="fas fa-chevron-down nav-arrow"></i>
            </a>
            <div class="collapse {{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') || request()->routeIs('configuracoes.*') ? 'show' : '' }}"
                id="{{ $p }}menuConfig">
                <div class="submenu">
                    @if ($isAdmin || Auth::user()->can('usuarios.gerir'))
                        <a href="{{ route('admin.users.index') }}"
                            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Usuários</a>
                    @endif
                    {{-- Mesma regra do DepartamentoController (User::podeGerirDepartamentos). --}}
                    @if (Auth::user()->podeGerirDepartamentos())
                        <a href="{{ route('departamentos.index') }}"
                            class="nav-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">Departamentos</a>
                    @endif
                    {{-- Gabinetes é exclusivo do administrador (GabineteController::ensureAdmin). --}}
                    @if ($isAdmin)
                        <a href="{{ route('gabinetes.index') }}"
                            class="nav-link {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}">Gabinetes</a>
                    @endif
                    @if ($isAdmin || Auth::user()->can('permissoes.gerir'))
                        <a href="{{ route('configuracoes.permissoes.index') }}"
                            class="nav-link {{ request()->routeIs('configuracoes.permissoes.*') ? 'active' : '' }}">Matriz de Acesso</a>
                    @endif
                    @if ($isAdmin)
                        <a href="{{ route('admin.instituicao.edit') }}"
                            class="nav-link {{ request()->routeIs('admin.instituicao.*') ? 'active' : '' }}">Instituição</a>
                        <a href="{{ route('admin.documento-especies.index') }}"
                            class="nav-link {{ request()->routeIs('admin.documento-especies.*') ? 'active' : '' }}">Espécies e Prazos</a>
                        <a href="{{ route('modelos-despacho.index') }}"
                            class="nav-link {{ request()->routeIs('modelos-despacho.*') ? 'active' : '' }}">Modelos de Despacho</a>
                    @endif
                    @if ($isAdmin || Auth::user()->can('configuracoes.editar'))
                        <a href="{{ route('empresas.index') }}"
                            class="nav-link {{ request()->routeIs('empresas.*') ? 'active' : '' }}">Empresas</a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ASSISTENTE IA --}}
    @if (config('app.feature_assistente') && Auth::user()->can('assistente.usar'))
        <hr class="sidebar-divider">
        <a href="{{ route('assistente.index') }}"
            class="nav-link {{ request()->routeIs('assistente.*') ? 'active' : '' }}" title="Assistente IA">
            <i class="fas fa-robot nav-icon"></i>
            <span>Assistente IA</span>
        </a>
    @endif

    {{-- APOIO --}}
    <hr class="sidebar-divider">
    <a href="{{ route('feedbacks.create') }}"
        class="nav-link {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}" title="Suporte">
        <i class="fas fa-headset nav-icon"></i>
        <span>Suporte</span>
    </a>
@endauth
