@php
    $user = Auth::user();
    $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
    $latest = $user ? $user->notifications()->latest()->limit(10)->get() : collect();
@endphp

<div class="dropdown" id="notificationsDropdown">
    <button class="btn btn-link text-decoration-none position-relative" id="notificationsDropdownToggle" type="button"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações">
        <i class="fas fa-bell fa-lg"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            id="notificationsBadge" style="font-size: .7rem;">
            {{ $unreadCount }}
        </span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 380px; max-width: 90vw;">
        <div class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom">
            <strong>Notificações</strong>
            <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-secondary" id="markAllReadBtn" type="button">
                    Marcar todas como lidas
                </button>
            </div>
        </div>
        <div class="list-group list-group-flush" id="notificationsList" style="max-height: 380px; overflow:auto;">
            @forelse($latest as $n)
                @php
                    $p = \App\Support\NotificationPresenter::present($n->data ?? []);
                    $title = $p['title'];
                    $url = $p['url'];
                    $isUnread = is_null($n->read_at);
                    $dotColor = $isUnread ? \App\Support\NotificationPresenter::priorityColor($p['priority']) : 'transparent';
                @endphp
                <a href="{{ $url ?: '#' }}"
                    class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item {{ $isUnread ? 'fw-semibold' : '' }}"
                    data-id="{{ $n->id }}" data-url="{{ $url }}">
                    <i class="fas fa-circle mt-1"
                        style="font-size: .5rem; color: {{ $dotColor }};"></i>
                    <div class="flex-grow-1">
                        <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                        <div class="text-wrap">{{ $title }}</div>
                    </div>
                </a>
            @empty
                <div class="p-3 text-center text-muted">Sem notificações.</div>
            @endforelse
        </div>
        <div class="p-2 text-center border-top small text-muted">
            <div class="d-flex justify-content-between align-items-center">
                <em>As mais recentes aparecem primeiro</em>
                <a href="{{ route('notifications.page') }}" class="btn btn-sm btn-primary">Ver todas</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const badge = document.getElementById('notificationsBadge');
        const list = document.getElementById('notificationsList');
        const POLL_INTERVAL = 30000; // 30 seconds

        // Escapa dados vindos do servidor antes de injetar via innerHTML (proteção XSS).
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function fetchNotifications() {
            fetch('{{ route('notifications.index') }}')
                .then(response => response.json())
                .then(data => {
                    updateBadge(data.unread_count);
                    updateList(data.notifications);
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        function updateBadge(count) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '0';
                // Optional: badge.classList.add('d-none'); to hide if 0
            }
        }

        function updateList(notifications) {
            if (notifications.length === 0) {
                list.innerHTML = '<div class="p-3 text-center text-muted">Sem notificações.</div>';
                return;
            }

            let html = '';
            notifications.forEach(n => {
                const isUnread = !n.read_at;
                const title = n.title || 'Notificação';
                const url = n.url || '#';
                const priority = n.priority || 'normal';
                const priorityColor = priority === 'urgent' ? '#dc3545'
                    : priority === 'high' ? '#fd7e14'
                    : priority === 'info' ? '#6c757d'
                    : '#0d6efd';
                const dotColor = isUnread ? priorityColor : 'transparent';
                const time = n.created_at_human ||
                'agora mesmo'; // Backend should provide human readable time or we compute

                html += `
                <a href="${escapeHtml(url)}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item ${isUnread ? 'fw-semibold' : ''}"
                   data-id="${escapeHtml(n.id)}">
                    <i class="fas fa-circle mt-1" style="font-size: .5rem; color: ${dotColor};"></i>
                    <div class="flex-grow-1">
                        <div class="small text-muted">${escapeHtml(time)}</div>
                        <div class="text-wrap">${escapeHtml(title)}</div>
                    </div>
                </a>`;
            });
            list.innerHTML = html;
        }

        // Tempo real via Laravel Echo (Reverb), quando configurado.
        // Ao receber uma notificação no canal privado do utilizador, re-sincroniza
        // imediatamente o sino. O polling mantém-se como fallback (Echo indisponível).
        const notificationsUserId = {{ auth()->id() ?? 'null' }};
        let echoConnected = false;
        if (window.Echo && notificationsUserId) {
            try {
                window.Echo.private('App.Models.User.' + notificationsUserId)
                    .notification(function() {
                        fetchNotifications();
                    });
                echoConnected = true;
            } catch (e) {
                console.error('Falha ao subscrever notificações em tempo real:', e);
            }
        }

        // Polling: rápido como único mecanismo; mais espaçado quando o Echo está ativo.
        setInterval(fetchNotifications, echoConnected ? 120000 : POLL_INTERVAL);

        // Click Handler for "Mark as Read" behavior
        list.addEventListener('click', function(e) {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            // Se for um link e tiver URL, vamos interceptar
            const url = item.getAttribute('href');
            const id = item.dataset.id;

            if (id && url && url !== '#') {
                e.preventDefault(); // Stop immediate navigation

                // Call backend to mark as read
                fetch(`/notifications/read/${id}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(() => {
                        // Navigate after marking as read
                        window.location.href = url;
                    })
                    .catch(err => {
                        console.error('Error marking read:', err);
                        // Navigate anyway even if error
                        window.location.href = url;
                    });
            }
        });

        // Handler for "Mark All as Read"
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('{{ route('notifications.read_all') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok) {
                            updateBadge(0);
                            fetchNotifications(); // Refresh list to show all read
                        }
                    });
            });
        }
    });
</script>
