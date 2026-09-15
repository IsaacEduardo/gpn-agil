{{--
    Navegação horizontal simplificada do EDMS (1 clique).
    Foco estrito: Início, Documentos Externos, Documentos Internos, Arquivo Digital, Administração.
--}}
@auth
@php
    $defaultTab = $menuCounts['default_tab'] ?? 'atribuidos_mim';
    $extCount = $menuCounts['ext_pendentes'] ?? 0;
    $intCount = $menuCounts['int_pendentes'] ?? 0;

    $isAdmin = Auth::user()->isAdmin();
    $canAdmin = $isAdmin || Auth::user()->canAny(['configuracoes.editar', 'usuarios.gerir', 'departamentos.gerir', 'permissoes.gerir']);
    $canManageTemplates = $isAdmin || Auth::user()->hasRole('admin') || (method_exists(Auth::user(), 'isChefeGabinete') && Auth::user()->isChefeGabinete()) || (method_exists(Auth::user(), 'isSuperChefeGabinete') && Auth::user()->isSuperChefeGabinete());

    $extActive = request()->routeIs('documentos-entradas.*') || request()->routeIs('tarefas.*');
    $intActive = request()->routeIs('documentos-internos.*') || request()->routeIs('modelos.*');
    $arqActive = request()->routeIs('edms.*') || request()->routeIs('pastas.*');
    $adminActive = request()->routeIs('admin.*') || request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('configuracoes.*');
@endphp

<nav class="gov-nav" aria-label="Navegação principal">
    <ul class="gov-nav__list">

        {{-- 1. INÍCIO (DASHBOARD) --}}
        <li class="gov-nav__item">
            <a href="{{ route('home') }}" class="gov-nav__link {{ request()->routeIs('home') ? 'gov-nav__link--active' : '' }}">
                <i class="fas fa-th-large"></i> Início
            </a>
        </li>

        {{-- 2. DOCUMENTOS EXTERNOS (LINK DIRETO 1-CLIQUE POR PERFIL) --}}
        <li class="gov-nav__item">
            <a href="{{ route('documentos-entradas.index', ['tab' => $defaultTab]) }}" 
               class="gov-nav__link {{ $extActive ? 'gov-nav__link--active' : '' }}" 
               title="Entradas de Documentos Externos">
                <i class="fas fa-inbox"></i> Documentos Externos
                @if ($extCount > 0)
                    <span class="badge rounded-pill bg-warning text-dark ms-2 shadow-sm">{{ $extCount }}</span>
                @endif
            </a>
        </li>

        {{-- 3. DOCUMENTOS INTERNOS (LINK DIRETO 1-CLIQUE) --}}
        <li class="gov-nav__item">
            <a href="{{ route('documentos-internos.index') }}" 
               class="gov-nav__link {{ request()->routeIs('documentos-internos.*') ? 'gov-nav__link--active' : '' }}" 
               title="Gestão de Memorandos, Informações Técnicas e Ofícios">
                <i class="fas fa-file-alt"></i> Documentos Internos
                @if ($intCount > 0)
                    <span class="badge rounded-pill bg-info text-dark ms-2 shadow-sm">{{ $intCount }}</span>
                @endif
            </a>
        </li>

        {{-- 4. TEMPLATES / MODELOS DE DOCUMENTOS (APENAS ADMIN E CHEFE DE GABINETE) --}}
        @if ($canManageTemplates)
            <li class="gov-nav__item">
                <a href="{{ route('modelos.index') }}" 
                   class="gov-nav__link {{ request()->routeIs('modelos.*') ? 'gov-nav__link--active' : '' }}" 
                   title="Gestão de Modelos e Templates Padronizados de Documentos">
                    <i class="fas fa-file-contract"></i> Templates
                </a>
            </li>
        @endif

        {{-- 5. ARQUIVO DIGITAL (LINK DIRETO 1-CLIQUE PARA O ACERVO/EDMS) --}}
        <li class="gov-nav__item">
            <a href="{{ route('edms.index') }}" 
               class="gov-nav__link {{ $arqActive ? 'gov-nav__link--active' : '' }}" 
               title="Repositório de Arquivos, Pastas e Documentos Arquivados">
                <i class="fas fa-archive"></i> Arquivo Digital
            </a>
        </li>

        {{-- 5.5 RELATÓRIOS & ANALYTICS --}}
        @if (Auth::user()->isAdmin() || Auth::user()->isChefeGabinete() || Auth::user()->isSuperChefeGabinete() || Auth::user()->isChefeDepartamento() || Auth::user()->can('relatorios.view'))
            <li class="gov-nav__item">
                <a href="{{ route('relatorios.index') }}" 
                   class="gov-nav__link {{ request()->routeIs('relatorios.*') ? 'gov-nav__link--active' : '' }}" 
                   title="Módulo de Relatórios, BI e Analytics EDMS">
                    <i class="fas fa-chart-line"></i> Relatórios
                </a>
            </li>
        @endif

        {{-- 6. SERVIÇOS & FROTA (DROPDOWN) --}}
        @php
            $servicosActive = request()->routeIs('viaturas.*') || request()->routeIs('credenciais.*');
        @endphp
        <li class="gov-nav__item dropdown">
            <a href="#" class="gov-nav__link {{ $servicosActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false" title="Gestão de Viaturas e Credenciais">
                <i class="fas fa-cubes"></i> Serviços & Frota <i class="fas fa-chevron-down gov-nav__caret"></i>
            </a>
            <ul class="dropdown-menu shadow-lg border-0 mt-2" style="border-radius: 8px;">
                <li>
                    <a class="dropdown-item py-2 {{ request()->routeIs('viaturas.*') ? 'active' : '' }}" href="{{ route('viaturas.index') }}">
                        <i class="fas fa-car me-2 text-muted"></i>Gestão de Viaturas
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2 {{ request()->routeIs('credenciais.*') ? 'active' : '' }}" href="{{ route('credenciais.index') }}">
                        <i class="fas fa-id-card me-2 text-muted"></i>Gestão de Credenciais
                    </a>
                </li>
            </ul>
        </li>

        {{-- 7. TERRITÓRIO & GESTÃO DE LOTES --}}
        @if (Auth::user()->canAny(['lotes.view', 'solicitacoes_lotes.view', 'requerentes.view']) || Auth::user()->isAdmin())
            @php
                $territorioActive = request()->routeIs('lotes.*') || request()->routeIs('solicitacoes.*') || request()->routeIs('requerentes.*') || request()->routeIs('analises-tecnicas.*');
            @endphp
            <li class="gov-nav__item dropdown">
                <a href="#" class="gov-nav__link {{ $territorioActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false" title="Gestão de Território e Lotes">
                    <i class="fas fa-map-marked-alt"></i> Território <i class="fas fa-chevron-down gov-nav__caret"></i>
                </a>
                <ul class="dropdown-menu shadow-lg border-0 mt-2" style="border-radius: 8px;">
                    @if (Auth::user()->can('lotes.view') || Auth::user()->isAdmin())
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('lotes.*') ? 'active' : '' }}" href="{{ route('lotes.index') }}">
                                <i class="fas fa-map-pin me-2 text-muted"></i>Gestão de Lotes
                            </a>
                        </li>
                    @endif
                    @if (Auth::user()->can('solicitacoes_lotes.view') || Auth::user()->isAdmin())
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('solicitacoes.*') ? 'active' : '' }}" href="{{ route('solicitacoes.index') }}">
                                <i class="fas fa-file-signature me-2 text-muted"></i>Solicitações de Atribuição
                            </a>
                        </li>
                    @endif
                    @if (Auth::user()->can('requerentes.view') || Auth::user()->isAdmin())
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('requerentes.*') ? 'active' : '' }}" href="{{ route('requerentes.index') }}">
                                <i class="fas fa-user-friends me-2 text-muted"></i>Requerentes
                            </a>
                        </li>
                    @endif
                </ul>
            </li>
        @endif

        {{-- 5. ADMINISTRAÇÃO (APENAS GESTORES / ADMINS) --}}
        @if ($canAdmin)
            <li class="gov-nav__item dropdown">
                <a href="#" class="gov-nav__link {{ $adminActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sliders"></i> Administração <i class="fas fa-chevron-down gov-nav__caret"></i>
                </a>
                <ul class="dropdown-menu shadow-lg border-0 mt-2" style="border-radius: 8px;">
                    @if ($isAdmin || Auth::user()->can('usuarios.gerir'))
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="fas fa-users-cog me-2 text-muted"></i>Gestão de Usuários</a></li>
                    @endif
                    @if ($isAdmin || Auth::user()->can('departamentos.gerir'))
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('departamentos.*') ? 'active' : '' }}" href="{{ route('departamentos.index') }}"><i class="fas fa-sitemap me-2 text-muted"></i>Departamentos</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}" href="{{ route('gabinetes.index') }}"><i class="fas fa-building me-2 text-muted"></i>Gabinetes</a></li>
                    @endif
                    @if ($isAdmin || Auth::user()->can('permissoes.gerir'))
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('configuracoes.permissoes.*') ? 'active' : '' }}" href="{{ route('configuracoes.permissoes.index') }}"><i class="fas fa-shield-alt me-2 text-muted"></i>Matriz de Acesso</a></li>
                    @endif
                    @if ($isAdmin)
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('admin.instituicao.*') ? 'active' : '' }}" href="{{ route('admin.instituicao.edit') }}"><i class="fas fa-landmark me-2 text-muted"></i>Dados da Instituição</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('admin.documento-especies.*') ? 'active' : '' }}" href="{{ route('admin.documento-especies.index') }}"><i class="fas fa-stopwatch me-2 text-muted"></i>Espécies e Prazos</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('modelos-despacho.*') ? 'active' : '' }}" href="{{ route('modelos-despacho.index') }}"><i class="fas fa-file-signature me-2 text-muted"></i>Modelos de Despacho</a></li>
                    @endif
                    @if ($isAdmin || Auth::user()->can('configuracoes.editar'))
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('empresas.*') ? 'active' : '' }}" href="{{ route('empresas.index') }}"><i class="fas fa-building-user me-2 text-muted"></i>Empresas Cadastradas</a></li>
                    @endif
                </ul>
            </li>
        @endif

        {{-- ASSISTENTE IA (OPCIONAL) --}}
        @if (config('app.feature_assistente') && Auth::user()->can('assistente.usar'))
            <li class="gov-nav__item">
                <a href="{{ route('assistente.index') }}" class="gov-nav__link {{ request()->routeIs('assistente.*') ? 'gov-nav__link--active' : '' }}">
                    <i class="fas fa-robot"></i> Assistente IA
                </a>
            </li>
        @endif

        {{-- APOIO / SUPORTE --}}
        <li class="gov-nav__item ms-auto">
            <a href="{{ route('feedbacks.create') }}" class="gov-nav__link {{ request()->routeIs('feedbacks.*') ? 'gov-nav__link--active' : '' }}">
                <i class="fas fa-headset"></i> Apoio
            </a>
        </li>

    </ul>
</nav>
@endauth
