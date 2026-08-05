{{--
    Navegação do menu lateral (partilhada entre o sidebar desktop e o offcanvas mobile).
    -----------------------------------------------------------------------------------
    Lógica de negócio (links, @can, @if, permissões, contadores $menuCounts) preservada
    integralmente do layout original. Apenas a apresentação foi modernizada via
    resources/sass/_sidebar.scss.

    $idPrefix  → prefixo aplicado aos IDs dos grupos colapsáveis (Bootstrap collapse).
                 Evita IDs duplicados quando este parcial é incluído duas vezes na
                 mesma página (desktop + offcanvas mobile). Default: '' (desktop).
--}}
@php($p = $idPrefix ?? '')

@auth
    {{-- DASHBOARD PRINCIPAL --}}
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" title="Dashboard">
        <i class="fas fa-th-large nav-icon"></i>
        <span>Dashboard</span>
    </a>

    {{-- ASSISTENTE IA (gated por feature flag + permissão) --}}
    @if (config('app.feature_assistente') && Auth::user()->can('assistente.usar'))
        <a href="{{ route('assistente.index') }}"
            class="nav-link {{ request()->routeIs('assistente.*') ? 'active' : '' }}" title="Assistente IA">
            <i class="fas fa-robot nav-icon"></i>
            <span>Assistente IA</span>
        </a>
    @endif

    {{-- GESTÃO EXECUTIVA --}}
    @if (Auth::user()->isChefeGabinete() || Auth::user()->isSuperChefeGabinete() || Auth::user()->hasPermissionTo('gabinete.view_all') || Auth::user()->isAdmin() || Auth::user()->hasRole('chefe-departamento') || Auth::user()->hasRole('chefe_departamento'))
        <hr class="sidebar-divider">
        <div class="nav-group-label">Gestão Executiva</div>

        {{-- GABINETE DASHBOARD (Apenas para Chefes de Gabinete, Super Chefes ou delegados) --}}
        @if (Auth::user()->isChefeGabinete() || Auth::user()->isSuperChefeGabinete() || Auth::user()->hasPermissionTo('gabinete.view_all') || Auth::user()->isAdmin())
            <a href="{{ route('gabinete.dashboard') }}"
                class="nav-link {{ request()->routeIs('gabinete.dashboard') ? 'active' : '' }}"
                title="Painel do Gabinete">
                <i class="fas fa-building-user nav-icon"></i>
                <span>Painel do Gabinete</span>
            </a>
        @endif

        {{-- DEPARTAMENTO DASHBOARD (Apenas para Chefes de Departamento) --}}
        @if (Auth::user()->hasRole('chefe-departamento') || Auth::user()->hasRole('chefe_departamento') || Auth::user()->isAdmin())
            <a href="{{ route('departamento.dashboard') }}"
                class="nav-link {{ request()->routeIs('departamento.dashboard') ? 'active' : '' }}"
                title="Gestão Departamental">
                <i class="fas fa-sitemap nav-icon"></i>
                <span>Gestão Departamental</span>
            </a>
        @endif
    @endif

    {{-- GESTÃO TERRITORIAL --}}
    @if (Auth::user()->can('viewAny', App\Models\Lote::class) || Auth::user()->can('viewAny', App\Models\SolicitacaoAtribuicao::class) || Auth::user()->can('viewAny', App\Models\Requerente::class))
        <hr class="sidebar-divider">
        <div class="nav-group-label">Gestão Territorial &amp; Infraestrutura</div>

        <div class="nav-item-wrapper">
            <a href="#{{ $p }}menuLotes" data-bs-toggle="collapse" title="Gestão de Terras"
                class="nav-link {{ request()->routeIs('lotes.*') || request()->routeIs('solicitacoes.*') || request()->routeIs('requerentes.*') ? 'active' : '' }}"
                aria-expanded="{{ request()->routeIs('lotes.*') || request()->routeIs('solicitacoes.*') || request()->routeIs('requerentes.*') ? 'true' : 'false' }}"
                aria-controls="{{ $p }}menuLotes">
                <i class="fas fa-map-marked-alt nav-icon"></i>
                <span>Gestão de Terras</span>
                <i class="fas fa-chevron-down nav-arrow"></i>
            </a>
            <div class="collapse {{ request()->routeIs('lotes.*') || request()->routeIs('solicitacoes.*') || request()->routeIs('requerentes.*') ? 'show' : '' }}" id="{{ $p }}menuLotes">
                <div class="submenu">
                    @can('viewAny', App\Models\Lote::class)
                        <a href="{{ route('lotes.index') }}"
                            class="nav-link {{ request()->routeIs('lotes.*') ? 'active' : '' }}">Inventário de Lotes</a>
                    @endcan
                    @can('viewAny', App\Models\SolicitacaoAtribuicao::class)
                        <a href="{{ route('solicitacoes.index') }}"
                            class="nav-link {{ request()->routeIs('solicitacoes.*') ? 'active' : '' }}">Solicitações de Atribuição</a>
                    @endcan
                    @can('viewAny', App\Models\Requerente::class)
                        <a href="{{ route('requerentes.index') }}"
                            class="nav-link {{ request()->routeIs('requerentes.*') ? 'active' : '' }}">Requerentes</a>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    {{-- SERVIÇOS OPERACIONAIS --}}
    @if (Auth::user()->can('viewAny', App\Models\Viatura::class) || Auth::user()->can('viewAny', App\Models\Requisicao::class))
        <hr class="sidebar-divider">
        <div class="nav-group-label">Serviços Operacionais</div>

        @can('viewAny', App\Models\Viatura::class)
            <a href="{{ route('viaturas.index') }}"
                class="nav-link {{ request()->routeIs('viaturas.*') ? 'active' : '' }}" title="Viaturas">
                <i class="fas fa-truck-fast nav-icon"></i>
                <span>Viaturas</span>
            </a>
        @endcan

        @can('viewAny', App\Models\Requisicao::class)
            <div class="nav-item-wrapper">
                <a href="#{{ $p }}menuReq" data-bs-toggle="collapse" title="Requisições"
                    class="nav-link {{ request()->routeIs('requisicoes.*') ? 'active' : '' }}"
                    aria-expanded="{{ request()->routeIs('requisicoes.*') ? 'true' : 'false' }}"
                    aria-controls="{{ $p }}menuReq">
                    <i class="fas fa-clipboard-list nav-icon"></i>
                    <span>Requisições</span>
                    <i class="fas fa-chevron-down nav-arrow"></i>
                    @if (!empty($menuCounts) && ($menuCounts['pend_requisicoes'] ?? 0) > 0)
                        <span class="badge-nav badge-warning ms-2">{{ $menuCounts['pend_requisicoes'] }}</span>
                    @endif
                </a>
                <div class="collapse {{ request()->routeIs('requisicoes.*') ? 'show' : '' }}" id="{{ $p }}menuReq">
                    <div class="submenu">
                        <a href="{{ route('requisicoes.index') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.index') ? 'active' : '' }}">Visão Geral</a>
                        <a href="{{ route('requisicoes.pendentes') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.pendentes') ? 'active' : '' }}">Pendentes</a>
                        <a href="{{ route('requisicoes.produtos.index') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.produtos.index') ? 'active' : '' }}">Produtos</a>
                        <a href="{{ route('requisicoes.oficina.index') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.oficina.index') ? 'active' : '' }}">Oficina</a>
                        <a href="{{ route('requisicoes.servico.index') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.servico.index') ? 'active' : '' }}">Serviços</a>
                        <a href="{{ route('requisicoes.passagem.index') }}"
                            class="nav-link {{ request()->routeIs('requisicoes.passagem.index') ? 'active' : '' }}">Passagem</a>
                    </div>
                </div>
            </div>
        @endcan
    @endif

    {{-- GESTÃO DOCUMENTAL --}}
    <hr class="sidebar-divider">
    <div class="nav-group-label">Gestão Documental</div>

    <a href="{{ route('tarefas.index') }}"
        class="nav-link {{ request()->routeIs('tarefas.*') ? 'active' : '' }}" title="Minhas Tarefas">
        <i class="fas fa-tasks nav-icon"></i>
        <span>Minhas Tarefas</span>
    </a>

    <a href="{{ route('documentos-entradas.index') }}"
        class="nav-link {{ request()->routeIs('documentos-entradas.*') && request('meus') !== 'pendentes_recebimento' && !in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado', 'visto_gabinete_pendente']) ? 'active' : '' }}" title="Caixa de Entrada">
        <i class="fas fa-inbox nav-icon"></i>
        <span>Caixa de Entrada</span>
    </a>

    <a href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento']) }}"
        class="nav-link {{ request('meus') === 'pendentes_recebimento' ? 'active' : '' }}" title="Por Receber">
        <i class="fas fa-clock nav-icon"></i>
        <span>Por Receber</span>
        @if (!empty($menuCounts) && ($menuCounts['pend_documentos_por_receber'] ?? 0) > 0)
            <span class="badge-nav badge-warning">{{ $menuCounts['pend_documentos_por_receber'] }}</span>
        @endif
    </a>

    <div class="nav-item-wrapper">
        <a href="#{{ $p }}menuVistos" data-bs-toggle="collapse" title="Vistos & Pareceres"
            class="nav-link {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado', 'visto_gabinete_pendente']) ? 'active' : '' }}"
            aria-expanded="{{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado', 'visto_gabinete_pendente']) ? 'true' : 'false' }}"
            aria-controls="{{ $p }}menuVistos">
            <i class="fas fa-stamp nav-icon"></i>
            <span>Vistos &amp; Pareceres</span>
            <i class="fas fa-chevron-down nav-arrow"></i>
        </a>
        <div class="collapse {{ request()->routeIs('documentos-entradas.*') && in_array(request('meus'), ['visto_pendente', 'visto_aprovado', 'visto_rejeitado', 'visto_gabinete_pendente']) ? 'show' : '' }}"
            id="{{ $p }}menuVistos">
            <div class="submenu">
                <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente']) }}"
                    class="nav-link {{ request('meus') === 'visto_pendente' ? 'active' : '' }}">
                    Pendentes Dept.
                    @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_departamento'] ?? 0) > 0)
                        <span class="badge-nav badge-warning">{{ $menuCounts['pend_documentos_visto_departamento'] }}</span>
                    @endif
                </a>
                <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente']) }}"
                    class="nav-link {{ request('meus') === 'visto_gabinete_pendente' ? 'active' : '' }}">
                    Pendentes Gab.
                    @if (!empty($menuCounts) && ($menuCounts['pend_documentos_visto_gabinete'] ?? 0) > 0)
                        <span class="badge-nav badge-warning">{{ $menuCounts['pend_documentos_visto_gabinete'] }}</span>
                    @endif
                </a>
                <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_aprovado']) }}"
                    class="nav-link {{ request('meus') === 'visto_aprovado' ? 'active' : '' }}">Histórico</a>
            </div>
        </div>
    </div>

    <div class="nav-item-wrapper">
        <a href="#{{ $p }}menuArq" data-bs-toggle="collapse" title="Arquivo & Modelos"
            class="nav-link {{ request()->routeIs('edms.*') || request()->routeIs('documentos-internos.*') || request()->routeIs('pastas.*') || request()->routeIs('modelos.*') ? 'active' : '' }}"
            aria-expanded="{{ request()->routeIs('edms.*') || request()->routeIs('documentos-internos.*') || request()->routeIs('pastas.*') || request()->routeIs('modelos.*') ? 'true' : 'false' }}"
            aria-controls="{{ $p }}menuArq">
            <i class="fas fa-folder-open nav-icon"></i>
            <span>Arquivo &amp; Modelos</span>
            <i class="fas fa-chevron-down nav-arrow"></i>
        </a>
        <div class="collapse {{ request()->routeIs('edms.*') || request()->routeIs('documentos-internos.*') || request()->routeIs('pastas.*') || request()->routeIs('modelos.*') ? 'show' : '' }}"
            id="{{ $p }}menuArq">
            <div class="submenu">
                <a href="{{ route('edms.index') }}"
                    class="nav-link {{ request()->routeIs('edms.*') ? 'active' : '' }}">Gerenciador EDMS</a>
                <a href="{{ route('documentos-internos.index') }}"
                    class="nav-link {{ request()->routeIs('documentos-internos.*') ? 'active' : '' }}">Internos (Legado)</a>
                <a href="{{ route('modelos.index') }}"
                    class="nav-link {{ request()->routeIs('modelos.*') ? 'active' : '' }}">Modelos</a>
            </div>
        </div>
    </div>

    {{-- ADMINISTRAÇÃO & CREDENCIAIS --}}
    <hr class="sidebar-divider">
    <div class="nav-group-label">Administração &amp; Credenciais</div>

    @can('viewAny', App\Models\ReservaEspaco::class)
        <a href="{{ route('reservas.index') }}"
            class="nav-link {{ request()->routeIs('reservas.*') ? 'active' : '' }}" title="Reservas">
            <i class="fas fa-calendar-check nav-icon"></i>
            <span>Reservas</span>
            @if (!empty($menuCounts) && ($menuCounts['pend_reservas'] ?? 0) > 0)
                <span class="badge-nav badge-warning">{{ $menuCounts['pend_reservas'] }}</span>
            @endif
        </a>
    @endcan

    <a href="{{ route('credenciais.index') }}"
        class="nav-link {{ request()->routeIs('credenciais.*') ? 'active' : '' }}" title="Credenciais">
        <i class="fas fa-id-card nav-icon"></i>
        <span>Credenciais</span>
    </a>

    <a href="{{ route('termos.index') }}"
        class="nav-link {{ request()->routeIs('termos.*') ? 'active' : '' }}" title="Termos e Declarações">
        <i class="fas fa-file-signature nav-icon"></i>
        <span>Termos e Declarações</span>
    </a>

    {{-- CONFIGURAÇÃO GERAL --}}
    @if (Auth::user()->canAny(['configuracoes.editar', 'usuarios.gerir', 'departamentos.gerir', 'permissoes.gerir']) ||
            Auth::user()->isAdmin())
        <hr class="sidebar-divider">
        <div class="nav-group-label">Configuração Geral</div>

        <div class="nav-item-wrapper">
            <a href="#{{ $p }}menuConfig" data-bs-toggle="collapse" title="Configurações do Sistema"
                class="nav-link {{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') ? 'active' : '' }}"
                aria-expanded="{{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') ? 'true' : 'false' }}"
                aria-controls="{{ $p }}menuConfig">
                <i class="fas fa-sliders nav-icon"></i>
                <span>Configurações do Sistema</span>
                <i class="fas fa-chevron-down nav-arrow"></i>
            </a>
            <div class="collapse {{ request()->routeIs('empresas.*') || request()->routeIs('departamentos.*') || request()->routeIs('gabinetes.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.instituicao.*') ? 'show' : '' }}"
                id="{{ $p }}menuConfig">
                <div class="submenu">
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.instituicao.edit') }}"
                            class="nav-link {{ request()->routeIs('admin.instituicao.*') ? 'active' : '' }}">Instituição</a>
                    @endif

                    @if (Auth::user()->can('configuracoes.editar') || Auth::user()->isAdmin())
                        <a href="{{ route('empresas.index') }}"
                            class="nav-link {{ request()->routeIs('empresas.*') ? 'active' : '' }}">Empresas</a>
                    @endif

                    @if (Auth::user()->can('departamentos.gerir') || Auth::user()->isAdmin())
                        <a href="{{ route('departamentos.index') }}"
                            class="nav-link {{ request()->routeIs('departamentos.*') ? 'active' : '' }}">Departamentos</a>
                    @endif

                    @if (Auth::user()->can('permissoes.gerir') || Auth::user()->isAdmin())
                        <a href="{{ route('configuracoes.permissoes.index') }}"
                            class="nav-link {{ request()->routeIs('configuracoes.permissoes.*') ? 'active' : '' }}">
                            Matriz de Acesso
                        </a>
                    @endif

                    @if (Auth::user()->can('departamentos.gerir') || Auth::user()->isAdmin())
                        <a href="{{ route('gabinetes.index') }}"
                            class="nav-link {{ request()->routeIs('gabinetes.*') ? 'active' : '' }}">Gabinetes</a>
                    @endif

                    @if (Auth::user()->can('usuarios.gerir') || Auth::user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}"
                            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Usuários</a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- APOIO --}}
    <hr class="sidebar-divider">
    <div class="nav-group-label">Apoio</div>

    <a href="{{ route('feedbacks.create') }}"
        class="nav-link {{ request()->routeIs('feedbacks.*') ? 'active' : '' }}" title="Suporte">
        <i class="fas fa-headset nav-icon"></i>
        <span>Suporte</span>
    </a>
@endauth
