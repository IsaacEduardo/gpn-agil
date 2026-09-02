@extends('layouts.app')

@section('breadcrumbs')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Minhas Notificações</li>
        </ol>
    </nav>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        {{-- Header da Página --}}
        <div class="card-header bg-white p-4 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-bell text-primary"></i> Minhas Notificações
                    </h1>
                    <p class="text-muted small mb-0">Histórico completo de alertas, tramitações e eventos do sistema.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('notifications.preferences.edit') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 rounded-3">
                        <i class="fas fa-sliders-h"></i> <span>Preferências</span>
                    </a>
                    <button id="markAllReadPageBtn" class="btn btn-primary btn-sm d-flex align-items-center gap-1 rounded-3">
                        <i class="fas fa-check-double"></i> <span>Marcar todas como lidas</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Abas de Filtro da Página --}}
        <div class="px-4 py-2 border-bottom bg-light d-flex gap-2">
            <a href="{{ route('notificacoes.index') }}"
                class="btn btn-sm {{ ($filter ?? 'all') === 'all' ? 'btn-primary shadow-sm fw-semibold' : 'btn-outline-secondary' }} rounded-pill px-3">
                Todas
            </a>
            <a href="{{ route('notificacoes.index', ['filter' => 'workflow']) }}"
                class="btn btn-sm {{ ($filter ?? '') === 'workflow' ? 'btn-primary shadow-sm fw-semibold' : 'btn-outline-secondary' }} rounded-pill px-3">
                <i class="fas fa-tasks me-1"></i> Tarefas & Despachos
            </a>
            <a href="{{ route('notificacoes.index', ['filter' => 'system']) }}"
                class="btn btn-sm {{ ($filter ?? '') === 'system' ? 'btn-primary shadow-sm fw-semibold' : 'btn-outline-secondary' }} rounded-pill px-3">
                <i class="fas fa-cogs me-1"></i> Sistema
            </a>
        </div>

        {{-- Lista de Notificações --}}
        <div class="list-group list-group-flush" id="notificationsListPage">
            @forelse($notifications as $n)
                @php
                    $p = \App\Support\NotificationPresenter::present($n->data ?? []);
                    $title = $p['title'];
                    $body = $p['body'];
                    $url = $p['url'];
                    $isUnread = is_null($n->read_at);
                    $icon = $p['icon'];
                    $iconBg = $p['icon_bg'];
                @endphp
                <a href="{{ $url ?: 'javascript:void(0)' }}"
                    class="list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 p-md-4 border-bottom notification-item {{ $isUnread ? 'bg-light-subtle fw-medium' : 'text-muted' }}"
                    data-id="{{ $n->id }}"
                    data-url="{{ $url }}">
                    
                    {{-- Ícone Contextual --}}
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 {{ $iconBg }}"
                        style="width: 44px; height: 44px; font-size: 1.1rem;">
                        <i class="{{ $icon }}"></i>
                    </div>

                    {{-- Conteúdo --}}
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-1 mb-1">
                            <h6 class="mb-0 text-dark {{ $isUnread ? 'fw-bold' : 'fw-normal' }}" style="font-size: 0.95rem;">
                                {{ $title }}
                            </h6>
                            <small class="text-muted text-nowrap" style="font-size: 0.8rem;">
                                <i class="far fa-clock me-1"></i>{{ $n->created_at->format('d/m/Y H:i') }} ({{ $n->created_at->diffForHumans() }})
                            </small>
                        </div>
                        @if($body && $body !== $title)
                            <p class="mb-0 text-secondary" style="font-size: 0.875rem; line-height: 1.45;">
                                {{ $body }}
                            </p>
                        @endif
                    </div>

                    {{-- Ponto Não Lido --}}
                    @if($isUnread)
                        <span class="rounded-circle bg-primary flex-shrink-0 mt-2 notif-unread-dot"
                            style="width: 10px; height: 10px;" title="Não lida"></span>
                    @endif
                </a>
            @empty
                <div class="p-5 text-center text-muted">
                    <div class="mb-3">
                        <i class="fas fa-bell-slash fs-1 text-muted opacity-50"></i>
                    </div>
                    <h5 class="fw-medium text-dark">Nenhuma notificação registrada</h5>
                    <p class="text-muted small mb-0">Você será avisado quando novas tarefas, despachos ou documentos forem atribuídos.</p>
                </div>
            @endforelse
        </div>

        @if(method_exists($notifications, 'links') && $notifications->hasPages())
            <div class="card-footer bg-white p-3 border-top">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clique em item: marca como lida e redireciona
    const list = document.getElementById('notificationsListPage');
    if (list) {
        list.addEventListener('click', function(e) {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            const id = item.dataset.id;
            const url = item.dataset.url;

            if (id) {
                item.classList.remove('bg-light-subtle', 'fw-medium');
                const dot = item.querySelector('.notif-unread-dot');
                if (dot) dot.remove();

                fetch(`/api/notificacoes/${encodeURIComponent(id)}/ler`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                }).catch(err => console.error('Erro ao marcar lida:', err));

                if (url && url !== '#' && !url.startsWith('javascript:')) {
                    window.location.href = url;
                }
            }
        });
    }

    // Marcar todas como lidas
    const markAllBtn = document.getElementById('markAllReadPageBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
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
                    document.querySelectorAll('#notificationsListPage .notification-item').forEach(item => {
                        item.classList.remove('bg-light-subtle', 'fw-medium');
                        const dot = item.querySelector('.notif-unread-dot');
                        if (dot) dot.remove();
                    });
                    if (window.Toast) {
                        window.Toast.success('Notificações', 'Todas as notificações foram marcadas como lidas.');
                    }
                }
            })
            .catch(err => console.error('Erro:', err));
        });
    }
});
</script>
@endsection