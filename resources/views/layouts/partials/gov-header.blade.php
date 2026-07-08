{{--
    Header institucional do Portal (brasão + ministério + ações) e navegação.
    Mantém os elementos funcionais existentes: pesquisa global (name="q" / #searchResults),
    ações rápidas, notificações e menu do utilizador.
--}}
@auth
<header class="gov-header">
    <div class="gov-header__top">
        <a href="{{ route('home') }}" class="gov-brand">
            <img src="{{ $dadosInstituicao->logo_url }}" alt="Brasão da {{ $dadosInstituicao->cabecalho_linha1 ?? 'República de Angola' }}" class="gov-brand__emblem">
            <span class="gov-brand__text">
                <span class="gov-brand__republic">{{ $dadosInstituicao->cabecalho_linha1 ?? 'República de Angola' }}</span>
                <span class="gov-brand__ministry">{{ $dadosInstituicao->nome_oficial ?? 'Governo Provincial do Namibe' }}</span>
            </span>
        </a>

        <div class="gov-header__actions">
            {{-- Pesquisa global (funcionalidade preservada) --}}
            <form id="globalSearchForm" class="gov-search" role="search">
                <i class="fas fa-search gov-search__icon"></i>
                <input type="search" name="q" class="gov-search__input" placeholder="Pesquisar..." autocomplete="off">
                <div id="searchResults" class="dropdown-menu w-100 shadow mt-2 border-0"
                    style="display:none; max-height: 400px; overflow-y: auto; border-radius: 6px;"></div>
            </form>

            {{-- Ações rápidas --}}
            <div class="dropdown d-none d-sm-block">
                <button class="btn btn--primary dropdown-toggle py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plus"></i> Novo
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 6px; min-width: 210px;">
                    <li><span class="dropdown-header text-uppercase small fw-bold text-muted">Ações Rápidas</span></li>
                    <li><a class="dropdown-item rounded py-2" href="{{ route('requisicoes.produtos.create.novo') }}"><i class="fas fa-box me-2 text-success"></i>Nova Requisição</a></li>
                    <li><a class="dropdown-item rounded py-2" href="{{ route('documentos-entradas.create') }}"><i class="fas fa-file-import me-2 text-success"></i>Entrada de Doc.</a></li>
                    <li><a class="dropdown-item rounded py-2" href="{{ route('reservas.create') }}"><i class="fas fa-calendar-plus me-2 text-warning"></i>Nova Reserva</a></li>
                </ul>
            </div>

            {{-- Notificações --}}
            <div class="position-relative">
                @include('components.notifications-dropdown')
            </div>

            {{-- Utilizador --}}
            <div class="dropdown">
                <a class="gov-user" data-bs-toggle="dropdown" role="button" aria-expanded="false" aria-label="Menu do utilizador">
                    <span class="gov-user__avatar">{{ substr(Auth::user()->name, 0, 1) }}</span>
                    <span class="d-none d-md-flex flex-column">
                        <span class="gov-user__name text-truncate" style="max-width: 160px;">{{ Auth::user()->name }}</span>
                        <span class="gov-user__role">Ver perfil</span>
                    </span>
                    <i class="fas fa-chevron-down ms-1 text-muted" style="font-size: .7rem;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="border-radius: 6px; min-width: 200px;">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user-circle me-2 text-muted"></i>Meu Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-power-off me-2"></i>Sair do Sistema</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    @include('layouts.partials.gov-nav')
</header>
@endauth
