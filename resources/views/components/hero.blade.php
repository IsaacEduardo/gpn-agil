<div class="dashboard-hero mb-4">
    <div>
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-tachometer-alt text-primary"></i>
            <h1 class="h4 hero-title mb-0">{{ $title ?? 'Dashboard' }}</h1>
        </div>
        @if(!empty($subtitle))
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="text-end">
        {{ $slot ?? '' }}
    </div>
</div>