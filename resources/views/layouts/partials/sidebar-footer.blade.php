{{--
    Rodapé do menu lateral — cartão do utilizador com dropdown (perfil / logout).
    Partilhado entre o sidebar desktop e o offcanvas mobile. Lógica preservada.
--}}
@auth
    <div class="sidebar-footer">
        <div class="dropdown dropup w-100">
            <div class="user-card" data-bs-toggle="dropdown" aria-expanded="false" role="button"
                aria-label="Menu do utilizador">
                <div class="user-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                <div class="user-info overflow-hidden">
                    <div class="fw-bold text-truncate" style="font-size: .9rem;">{{ Auth::user()->name }}</div>
                    <div class="text-white-50 text-truncate" style="font-size: .75rem;">Ver perfil</div>
                </div>
                <i class="fas fa-chevron-up ms-auto text-white-50" style="font-size: .7rem;"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-dark shadow-lg border-0 mb-2 w-100">
                <li>
                    <a class="dropdown-item" href="{{ route('profile.edit') }}">
                        <i class="fas fa-user-circle me-2"></i>Meu Perfil
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider border-secondary">
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fas fa-power-off me-2"></i>Sair do Sistema
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
@endauth
