@php
    $user = Auth::user();
    $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
    $latest = $user ? $user->notifications()->latest()->limit(15)->get() : collect();
@endphp

<div class="dropdown" id="notificationsDropdown">
    {{-- Botão do Sino com Badge Reativa --}}
    <button class="gov-nav__icon-btn position-relative" id="notificationsDropdownToggle" type="button"
        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Central de Notificações" title="Notificações">
        <i class="fas fa-bell fs-5 text-secondary"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $unreadCount > 0 ? '' : 'd-none' }}"
            id="notificationsBadge" style="font-size: 0.65rem; padding: 0.25em 0.55em; transform: translate(-30%, -20%) !important;">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
    </button>

    {{-- Dropdown Container Moderno --}}
    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-0 mt-2 notifications-dropdown-menu"
        style="width: 390px; max-width: 92vw; z-index: 1060;">
        
        {{-- Cabeçalho --}}
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top-4">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark">Notificações</h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 {{ $unreadCount > 0 ? '' : 'd-none' }}"
                    id="notificationsUnreadPill" style="font-size: 0.7rem;">
                    {{ $unreadCount }} nova(s)
                </span>
            </div>
            <button class="btn btn-sm btn-link text-decoration-none text-muted p-0 mark-all-read-btn d-flex align-items-center gap-1"
                id="markAllReadBtn" type="button" title="Marcar todas como lidas" style="font-size: 0.78rem;">
                <i class="fas fa-check-double text-primary"></i>
                <span>Marcar lidas</span>
            </button>
        </div>

        {{-- Abas de Filtro --}}
        <div class="px-3 pt-2 pb-1 border-bottom bg-white">
            <ul class="nav nav-pills nav-fill gap-1" id="notificationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-1 px-2 text-nowrap rounded-3 notif-tab-btn" data-filter="all" type="button" role="tab">
                        Todas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-2 text-nowrap rounded-3 notif-tab-btn" data-filter="workflow" type="button" role="tab">
                        Tarefas & Despachos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-2 text-nowrap rounded-3 notif-tab-btn" data-filter="system" type="button" role="tab">
                        Sistema
                    </button>
                </li>
            </ul>
        </div>

        {{-- Lista de Notificações com Scroll Suave --}}
        <div class="list-group list-group-flush notifications-scrollable" id="notificationsList"
            style="max-height: 380px; overflow-y: auto; scrollbar-width: thin;">
            @forelse($latest as $n)
                @php
                    $p = \App\Support\NotificationPresenter::present($n->data ?? []);
                    $title = $p['title'];
                    $body = $p['body'];
                    $url = $p['url'];
                    $isUnread = is_null($n->read_at);
                    $category = $p['category'];
                    $icon = $p['icon'];
                    $iconBg = $p['icon_bg'];
                @endphp
                <a href="{{ $url ?: 'javascript:void(0)' }}"
                    class="list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 border-bottom notification-item {{ $isUnread ? 'bg-light-subtle fw-medium' : 'text-muted' }}"
                    data-id="{{ $n->id }}"
                    data-category="{{ $category }}"
                    data-url="{{ $url }}">
                    
                    {{-- Ícone Contextual --}}
                    <div class="notification-icon-wrap rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 {{ $iconBg }}"
                        style="width: 36px; height: 36px; font-size: 0.9rem;">
                        <i class="{{ $icon }}"></i>
                    </div>

                    {{-- Conteúdo do Card --}}
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <h6 class="mb-0 text-dark text-truncate {{ $isUnread ? 'fw-bold' : 'fw-normal' }}" style="font-size: 0.84rem;">
                                {{ $title }}
                            </h6>
                            <small class="text-muted text-nowrap ms-2" style="font-size: 0.7rem;">
                                {{ $n->created_at->diffForHumans() }}
                            </small>
                        </div>
                        @if($body && $body !== $title)
                            <p class="mb-1 text-secondary text-truncate-2" style="font-size: 0.78rem; line-height: 1.35;">
                                {{ $body }}
                            </p>
                        @endif
                    </div>

                    {{-- Ponto Indicador Não Lido --}}
                    @if($isUnread)
                        <span class="rounded-circle bg-primary flex-shrink-0 mt-1 notif-unread-dot"
                            style="width: 8px; height: 8px;" title="Não lida"></span>
                    @endif
                </a>
            @empty
                {{-- Empty State --}}
                <div class="p-4 text-center text-muted empty-notifications">
                    <div class="mb-2">
                        <i class="fas fa-bell-slash fs-2 text-muted opacity-50"></i>
                    </div>
                    <div class="fw-medium text-dark" style="font-size: 0.875rem;">Tudo em dia!</div>
                    <small class="text-muted">Nenhuma notificação pendente no momento.</small>
                </div>
            @endforelse
        </div>

        {{-- Rodapé com Link para Histórico Completo --}}
        <div class="p-2 border-top bg-light text-center rounded-bottom-4">
            <a href="{{ route('notificacoes.index') }}" class="btn btn-sm btn-link text-decoration-none text-primary fw-semibold w-100 py-1" style="font-size: 0.8rem;">
                Ver histórico completo <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<style>
    .notif-tab-btn {
        font-size: 0.75rem;
        font-weight: 500;
        color: #64748b;
        background: transparent;
        border: none;
        transition: all 0.2s ease;
    }
    .notif-tab-btn:hover {
        color: #0f172a;
        background: #f1f5f9;
    }
    .notif-tab-btn.active {
        color: #0f172a !important;
        background: #e2e8f0 !important;
        font-weight: 600;
    }
    .notifications-scrollable::-webkit-scrollbar {
        width: 5px;
    }
    .notifications-scrollable::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .notification-item {
        transition: background-color 0.15s ease;
    }
    .notification-item:hover {
        background-color: #f8fafc !important;
    }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const badge = document.getElementById('notificationsBadge');
        const unreadPill = document.getElementById('notificationsUnreadPill');
        const list = document.getElementById('notificationsList');
        const tabButtons = document.querySelectorAll('.notif-tab-btn');
        const markAllBtn = document.getElementById('markAllReadBtn');
        const POLL_INTERVAL = 30000; // 30s
        let currentFilter = 'all';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateBadge(count) {
            const num = parseInt(count, 10) || 0;
            if (num > 0) {
                if (badge) {
                    badge.textContent = num > 99 ? '99+' : num;
                    badge.classList.remove('d-none');
                }
                if (unreadPill) {
                    unreadPill.textContent = `${num} nova(s)`;
                    unreadPill.classList.remove('d-none');
                }
            } else {
                if (badge) {
                    badge.textContent = '0';
                    badge.classList.add('d-none');
                }
                if (unreadPill) {
                    unreadPill.classList.add('d-none');
                }
            }
        }

        function renderNotifications(notifications) {
            if (!notifications || notifications.length === 0) {
                list.innerHTML = `
                    <div class="p-4 text-center text-muted empty-notifications">
                        <div class="mb-2">
                            <i class="fas fa-bell-slash fs-2 text-muted opacity-50"></i>
                        </div>
                        <div class="fw-medium text-dark" style="font-size: 0.875rem;">Tudo em dia!</div>
                        <small class="text-muted">Nenhuma notificação nesta categoria.</small>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(n => {
                const isUnread = !n.is_read;
                const title = n.title || 'Notificação';
                const body = n.body && n.body !== title ? n.body : '';
                const url = n.url || 'javascript:void(0)';
                const time = n.created_at_human || 'agora mesmo';
                const icon = n.icon || 'fas fa-bell';
                const iconBg = n.icon_bg || 'bg-light text-secondary border';

                html += `
                    <a href="${escapeHtml(url)}"
                       class="list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 border-bottom notification-item ${isUnread ? 'bg-light-subtle fw-medium' : 'text-muted'}"
                       data-id="${escapeHtml(n.id)}"
                       data-category="${escapeHtml(n.category || 'all')}"
                       data-url="${escapeHtml(url)}">
                        
                        <div class="notification-icon-wrap rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ${escapeHtml(iconBg)}"
                             style="width: 36px; height: 36px; font-size: 0.9rem;">
                            <i class="${escapeHtml(icon)}"></i>
                        </div>

                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <h6 class="mb-0 text-dark text-truncate ${isUnread ? 'fw-bold' : 'fw-normal'}" style="font-size: 0.84rem;">
                                    ${escapeHtml(title)}
                                </h6>
                                <small class="text-muted text-nowrap ms-2" style="font-size: 0.7rem;">
                                    ${escapeHtml(time)}
                                </small>
                            </div>
                            ${body ? `<p class="mb-1 text-secondary text-truncate-2" style="font-size: 0.78rem; line-height: 1.35;">${escapeHtml(body)}</p>` : ''}
                        </div>

                        ${isUnread ? '<span class="rounded-circle bg-primary flex-shrink-0 mt-1 notif-unread-dot" style="width: 8px; height: 8px;" title="Não lida"></span>' : ''}
                    </a>
                `;
            });

            list.innerHTML = html;
        }

        function fetchNotifications(filter = currentFilter) {
            fetch(`/api/notificacoes?filter=${encodeURIComponent(filter)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.notifications) {
                    updateBadge(data.unread_count);
                    renderNotifications(data.notifications);
                }
            })
            .catch(err => console.error('Erro ao atualizar notificações:', err));
        }

        // Filtro por abas
        tabButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                tabButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter || 'all';
                fetchNotifications(currentFilter);
            });
        });

        // Clique no item de notificação: Marca como lido e redireciona
        list.addEventListener('click', function(e) {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            const url = item.dataset.url;
            const id = item.dataset.id;

            if (id) {
                // Remove ponto e estilo não-lido de imediato no DOM
                item.classList.remove('bg-light-subtle', 'fw-medium');
                const dot = item.querySelector('.notif-unread-dot');
                if (dot) dot.remove();

                // Envia requisição para marcar como lida
                fetch(`/api/notificacoes/${encodeURIComponent(id)}/ler`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.unread_count !== undefined) {
                        updateBadge(data.unread_count);
                    }
                })
                .catch(err => console.error('Erro ao marcar notificação como lida:', err));

                if (url && url !== '#' && !url.startsWith('javascript:')) {
                    window.location.href = url;
                }
            }
        });

        // Marcar todas como lidas
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('/api/notificacoes/ler-todas', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.ok || data.status === 'success') {
                        updateBadge(0);
                        if (window.Toast) {
                            window.Toast.success('Notificações', 'Todas as notificações foram marcadas como lidas.');
                        }
                        fetchNotifications(currentFilter);
                    }
                })
                .catch(err => console.error('Erro ao marcar todas como lidas:', err));
            });
        }

        // Subscrição em tempo real via Echo (Reverb) quando disponível
        const userId = {{ auth()->id() ?? 'null' }};
        let echoActive = false;
        if (window.Echo && typeof window.Echo.private === 'function' && userId) {
            try {
                window.Echo.private('App.Models.User.' + userId)
                    .notification(function(notification) {
                        fetchNotifications(currentFilter);
                        if (window.Toast && notification) {
                            const title = notification.title || notification.titulo || 'Nova Notificação';
                            const body = notification.body || notification.message || notification.mensagem || '';
                            window.Toast.info(title, body, {
                                action: notification.url || notification.action_url ? {
                                    text: 'Abrir',
                                    url: notification.url || notification.action_url
                                } : null
                            });
                        }
                    });
                echoActive = true;
            } catch (e) {
                console.error('Falha ao inicializar Echo nas notificações:', e);
            }
        }

        // Polling contínuo
        setInterval(() => {
            fetchNotifications(currentFilter);
        }, echoActive ? 90000 : POLL_INTERVAL);
    });
</script>
