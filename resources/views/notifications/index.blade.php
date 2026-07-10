@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="fas fa-bell me-2"></i>Minhas notificações</h1>
        <div>
            <button id="markAllReadPageBtn" class="btn btn-sm btn-outline-secondary">
                Marcar todas como lidas
            </button>
        </div>
    </div>

    <div class="card">
        <div class="list-group list-group-flush" id="notificationsListPage">
            @forelse($notifications as $n)
                @php
                    $p = \App\Support\NotificationPresenter::present($n->data ?? []);
                    $title = $p['title'];
                    $url = $p['url'];
                    $isUnread = is_null($n->read_at);
                    $dotColor = $isUnread ? \App\Support\NotificationPresenter::priorityColor($p['priority']) : 'transparent';
                @endphp
                <a href="{{ $url ?: '#' }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-start notification-item {{ $isUnread ? 'fw-semibold' : '' }}" data-id="{{ $n->id }}" data-url="{{ $url }}">
                    <i class="fas fa-circle mt-1" style="font-size:.5rem;color: {{ $dotColor }}"></i>
                    <div class="flex-grow-1">
                        <div class="small text-muted">{{ $n->created_at->format('d/m/Y H:i') }}</div>
                        <div class="text-wrap">{{ $title }}</div>
                        @if($p['body'])<div class="small text-muted text-wrap">{{ $p['body'] }}</div>@endif
                    </div>
                </a>
            @empty
                <div class="p-3 text-center text-muted">Sem notificações.</div>
            @endforelse
        </div>
        @if(method_exists($notifications, 'links'))
        <div class="card-footer">{{ $notifications->links() }}</div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function(){
    // Garante o envio do token CSRF em todas as requisições AJAX desta página
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Clique em item: marca como lida e segue link
    $(document).on('click', '.notification-item', async function (e) {
        const id = $(this).data('id');
        const url = $(this).data('url');
        try {
            await $.post("{{ url('/notifications/read') }}/" + id);
            $(this).removeClass('fw-semibold').find('.fa-circle').css('color','transparent');
            // também atualiza badge do dropdown se existir
            const $badge = $('#notificationsBadge');
            if ($badge.length) {
                const current = parseInt($badge.text() || '0', 10);
                if (current > 0) $badge.text(current - 1);
            }
        } catch (err) { /* ignore */ }
        if (!url || url === '#') e.preventDefault();
    });

    // Marcar todas como lidas
    $('#markAllReadPageBtn').on('click', async function(){
        try {
            await $.post("{{ route('notifications.read_all') }}");
            $('#notificationsListPage .notification-item').removeClass('fw-semibold').find('.fa-circle').css('color','transparent');
            const $badge = $('#notificationsBadge');
            if ($badge.length) $badge.text('0');
        } catch (e) { /* ignore */ }
    });
});
</script>
@endsection