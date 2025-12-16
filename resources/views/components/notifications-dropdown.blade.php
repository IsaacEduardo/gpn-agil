@php
    $user = Auth::user();
    $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
    $latest = $user ? $user->notifications()->latest()->limit(10)->get() : collect();
@endphp

<div class="dropdown" id="notificationsDropdown">
    <button class="btn btn-link text-decoration-none position-relative" id="notificationsDropdownToggle"
            type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações">
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
                    $data = $n->data ?? [];
                    $title = $data['title'] ?? ($data['message'] ?? 'Nova notificação');
                    $url = $data['url'] ?? null;
                    $isUnread = is_null($n->read_at);
                @endphp
                <a href="{{ $url ?: '#' }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item {{ $isUnread ? 'fw-semibold' : '' }}"
                   data-id="{{ $n->id }}" data-url="{{ $url }}">
                    <i class="fas fa-circle mt-1" style="font-size: .5rem; color: {{ $isUnread ? '#0d6efd' : 'transparent' }};"></i>
                    <div class="flex-grow-1">
                        <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                        <div class="text-wrap">{!! $title !!}</div>
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