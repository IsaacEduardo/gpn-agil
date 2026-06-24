{{--
    Navegação horizontal do Portal (substitui a sidebar).
    Mesma lógica de permissões/links do menu original — apenas a apresentação muda.
    Os contadores ($menuCounts) e diretivas @can/@if foram preservados.
--}}
@auth
@php
    $isExec = Auth::user()->isChefeGabinete() || Auth::user()->isSuperChefeGabinete() || Auth::user()->hasPermissionTo('gabinete.view_all') || Auth::user()->isAdmin() || Auth::user()->hasRole('chefe-departamento') || Auth::user()->hasRole('chefe_departamento');
    $canServicos = Auth::user()->can('viewAny', App\Models\Viatura::class) || Auth::user()->can('viewAny', App\Models\Requisicao::class);
    $canConfig = Auth::user()->canAny(['configuracoes.editar', 'usuarios.gerir', 'departamentos.gerir', 'permissoes.gerir']) || Auth::user()->isAdmin();
    $docsActive = request()->routeIs('tarefas.*') || request()->routeIs('documentos-entradas.*') || request()->routeIs('edms.*') || request()->routeIs('documentos-internos.*') || request()->routeIs('pastas.*') || request()->routeIs('modelos.*');
    $adminActive = request()->routeIs('reservas.*') || request()->routeIs('credenciais.*') || request()->routeIs('termos.*');
    $configActive = request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*');
@endphp

<nav class="gov-nav" aria-label="Navegação principal">
    <ul class="gov-nav__list">

        {{-- INÍCIO --}}
        <li class="gov-nav__item">
            <a href="{{ route('home') }}" class="gov-nav__link {{ request()->routeIs('home') ? 'gov-nav__link--active' : '' }}">
                <i class="fas fa-th-large"></i> Início
            </a>
        </li>

        {{-- ASSISTENTE IA --}}
        @if (config('app.feature_assistente') && Auth::user()->can('assistente.usar'))
            <li class="gov-nav__item">
                <a href="{{ route('assistente.index') }}" class="gov-nav__link {{ request()->routeIs('assistente.*') ? 'gov-nav__link--active' : '' }}">
                    <i class="fas fa-robot"></i> Assistente
                </a>
            </li>
        @endif

        {{-- GESTÃO EXECUTIVA --}}
        @if ($isExec)
            <li class="gov-nav__item dropdown">
                <a href="#" class="gov-nav__link {{ request()->routeIs('gabinete.dashboard') || request()->routeIs('departamento.dashboard') ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-briefcase"></i> Gestão <i class="fas fa-chevron-down gov-nav__caret"></i>
                </a>
                <ul class="dropdown-menu">
                    @if (Auth::user()->isChefeGabinete() || Auth::user()->isSuperChefeGabinete() || Auth::user()->hasPermissionTo('gabinete.view_all') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('gabinete.dashboard') ? 'active' : '' }}" href="{{ route('gabinete.dashboard') }}"><i class="fas fa-building-user me-2 text-muted"></i>Painel do Gabinete</a></li>
                    @endif
                    @if (Auth::user()->hasRole('chefe-departamento') || Auth::user()->hasRole('chefe_departamento') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('departamento.dashboard') ? 'active' : '' }}" href="{{ route('departamento.dashboard') }}"><i class="fas fa-sitemap me-2 text-muted"></i>Gestão Departamental</a></li>
                    @endif
                </ul>
            </li>
        @endif

        {{-- SERVIÇOS OPERACIONAIS --}}
        @if ($canServicos)
            <li class="gov-nav__item dropdown">
                <a href="#" class="gov-nav__link {{ request()->routeIs('viaturas.*') || request()->routeIs('requisicoes.*') ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-truck-fast"></i> Serviços <i class="fas fa-chevron-down gov-nav__caret"></i>
                </a>
                <ul class="dropdown-menu">
                    @can('viewAny', App\Models\Viatura::class)
                        <li><a class="dropdown-item {{ request()->routeIs('viaturas.*') ? 'active' : '' }}" href="{{ route('viaturas.index') }}"><i class="fas fa-truck-fast me-2 text-muted"></i>Viaturas</a></li>
                    @endcan
                    @can('viewAny', App\Models\Requisicao::class)
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header text-uppercase small">Requisições</h6></li>
                        <li><a class="dropdown-item {{ request()->routeIs('requisicoes.index') ? 'active' : '' }}" href="{{ route('requisicoes.index') }}">Visão Geral</a></li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('requisicoes.pendentes') ? 'active' : '' }}" href="{{ route('requisicoes.pendentes') }}">
                                Pendentes
                                @if (!empty($menuCounts) && ($menuCounts['pend_requisicoes'] ?? 0) > 0)
                                    <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $menuCounts['pend_requisicoes'] }}</span>
                                @endif
                            </a>
                        </li>
                        <li><a class="dropdown-item {{ request()->routeIs('requisicoes.produtos.index') ? 'active' : '' }}" href="{{ route('requisicoes.produtos.index') }}">Produtos</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('requisicoes.oficina.index') ? 'active' : '' }}" href="{{ route('requisicoes.oficina.index') }}">Oficina</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('requisicoes.servico.index') ? 'active' : '' }}" href="{{ route('requisicoes.servico.index') }}">Serviços</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('requisicoes.passagem.index') ? 'active' : '' }}" href="{{ route('requisicoes.passagem.index') }}">Passagem</a></li>
                    @endcan
                </ul>
            </li>
        @endif

        {{-- GESTÃO DOCUMENTAL --}}
        <li class="gov-nav__item dropdown">
            <a href="#" class="gov-nav__link {{ $docsActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-folder-open"></i> Documentos <i class="fas fa-chevron-down gov-nav__caret"></i>
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item {{ request()->routeIs('tarefas.*') ? 'active' : '' }}" href="{{ route('tarefas.index') }}"><i class="fas fa-tasks me-2 text-muted"></i>Minhas Tarefas</a></li>
                <li><a class="dropdown-item {{ request()->routeIs('documentos-entradas.*') && request('meus') !== 'pendentes_recebimento' && !in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado', 'visto_gabinete_pendente']) ? 'active' : '' }}" href="{{ route('documentos-entradas.index') }}"><i class="fas fa-inbox me-2 text-muted"></i>Caixa de Entrada</a></li>
                <li>
                    <a class="dropdown-item {{ request('meus') === 'pendentes_recebimento' ? 'active' : '' }}" href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento']) }}">
                        <i class="fas fa-clock me-2 text-muted"></i>Por Receber
                        @if (!empty($menuCounts) && ($menuCounts['pend_documentos_por_receber'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $menuCounts['pend_documentos_por_receber'] }}</span>
                        @endif
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-uppercase small">Vistos &amp; Pareceres</h6></li>
                <li>
                    <a class="dropdown-item {{ request('meus') === 'visto_pendente' ? 'active' : '' }}" href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}">
                        Pendentes Dept.
                        @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                        @endif
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ request('meus') === 'visto_gabinete_pendente' ? 'active' : '' }}" href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente']) }}">
                        Pendentes Gab.
                        @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_gabinete'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $menuCounts['pend_documentos_visto_gabinete'] }}</span>
                        @endif
                    </a>
                </li>
                <li><a class="dropdown-item {{ request('meus') === 'visto_aprovado' ? 'active' : '' }}" href="{{ route('documentos-entradas.index', ['meus' => 'visto_aprovado']) }}">Histórico</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-uppercase small">Arquivo &amp; Modelos</h6></li>
                <li><a class="dropdown-item {{ request()->routeIs('edms.*') ? 'active' : '' }}" href="{{ route('edms.index') }}">Gerenciador EDMS</a></li>
                <li><a class="dropdown-item {{ request()->routeIs('documentos-internos.*') ? 'active' : '' }}" href="{{ route('documentos-internos.index') }}">Internos (Legado)</a></li>
                <li><a class="dropdown-item {{ request()->routeIs('modelos.*') ? 'active' : '' }}" href="{{ route('modelos.index') }}">Modelos</a></li>
            </ul>
        </li>

        {{-- ADMINISTRAÇÃO & CREDENCIAIS --}}
        <li class="gov-nav__item dropdown">
            <a href="#" class="gov-nav__link {{ $adminActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-id-card"></i> Administração <i class="fas fa-chevron-down gov-nav__caret"></i>
            </a>
            <ul class="dropdown-menu">
                @can('viewAny', App\Models\ReservaEspaco::class)
                    <li>
                        <a class="dropdown-item {{ request()->routeIs('reservas.*') ? 'active' : '' }}" href="{{ route('reservas.index') }}">
                            <i class="fas fa-calendar-check me-2 text-muted"></i>Reservas
                            @if (!empty($menuCounts) && ($menuCounts['pend_reservas'] ?? 0) > 0)
                                <span class="badge rounded-pill bg-warning text-dark ms-2">{{ $menuCounts['pend_reservas'] }}</span>
                            @endif
                        </a>
                    </li>
                @endcan
                <li><a class="dropdown-item {{ request()->routeIs('credenciais.*') ? 'active' : '' }}" href="{{ route('credenciais.index') }}"><i class="fas fa-id-card me-2 text-muted"></i>Credenciais</a></li>
                <li><a class="dropdown-item {{ request()->routeIs('termos.*') ? 'active' : '' }}" href="{{ route('termos.index') }}"><i class="fas fa-file-signature me-2 text-muted"></i>Termos e Declarações</a></li>
            </ul>
        </li>

        {{-- CONFIGURAÇÃO GERAL --}}
        @if ($canConfig)
            <li class="gov-nav__item dropdown">
                <a href="#" class="gov-nav__link {{ $configActive ? 'gov-nav__link--active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sliders"></i> Configuração <i class="fas fa-chevron-down gov-nav__caret"></i>
                </a>
                <ul class="dropdown-menu">
                    @if (Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('admin.instituicao.*') ? 'active' : '' }}" href="{{ route('admin.instituicao.edit') }}">Instituição</a></li>
                    @endif
                    @if (Auth::user()->can('configuracoes.editar') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('empresas.*') ? 'active' : '' }}" href="{{ route('empresas.index') }}">Empresas</a></li>
                    @endif
                    @if (Auth::user()->can('departamentos.gerir') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('departamentos.*') ? 'active' : '' }}" href="{{ route('departamentos.index') }}">Departamentos</a></li>
                    @endif
                    @if (Auth::user()->can('permissoes.gerir') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('configuracoes.permissoes.*') ? 'active' : '' }}" href="{{ route('configuracoes.permissoes.index') }}">Matriz de Acesso</a></li>
                    @endif
                    @if (Auth::user()->can('departamentos.gerir') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}" href="{{ route('gabinetes.index') }}">Gabinetes</a></li>
                    @endif
                    @if (Auth::user()->can('usuarios.gerir') || Auth::user()->isAdmin())
                        <li><a class="dropdown-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Usuários</a></li>
                    @endif
                </ul>
            </li>
        @endif

        {{-- APOIO --}}
        <li class="gov-nav__item">
            <a href="{{ route('feedbacks.create') }}" class="gov-nav__link {{ request()->routeIs('feedbacks.*') ? 'gov-nav__link--active' : '' }}">
                <i class="fas fa-headset"></i> Apoio
            </a>
        </li>

    </ul>
</nav>
@endauth
