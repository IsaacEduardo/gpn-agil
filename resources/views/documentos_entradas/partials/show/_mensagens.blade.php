        <!-- Feedback Messages -->
        @php($currentDept = optional($doc->departamento)->nome)
        @php($currentStatus = ucfirst($doc->status))
        
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('success') }}</div>
                    <div class="small opacity-75">Departamento atual: {{ $currentDept ?? '—' }} • Status: {{ $currentStatus }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        @if (session('info'))
            <div class="alert alert-info alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-info-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('info') }}</div>
                    <div class="small opacity-75">Departamento atual: {{ $currentDept ?? '—' }} • Status: {{ $currentStatus }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('warning') }}</div>
                    <div class="small opacity-75">Verifique os dados antes de continuar.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error') || session('danger'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-times-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('error') ?? session('danger') }}</div>
                    <div class="small opacity-75">Se persistir, contate o administrador.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-times-circle fs-4 me-3"></i>
                    <div class="fw-semibold">Ocorreram problemas com sua solicitação:</div>
                </div>
                <ul class="mb-0 small">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
