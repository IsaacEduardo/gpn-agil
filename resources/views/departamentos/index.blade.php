@extends('layouts.app')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}"
                        class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Departamentos</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">
        {{-- Header & Actions --}}
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fas fa-sitemap text-primary me-2"></i>Departamentos</h2>
                <p class="text-muted mb-0 small">Gerencie os departamentos da instituição.</p>
            </div>
            <a href="{{ route('departamentos.create') }}" class="btn btn-primary shadow-sm fw-medium">
                <i class="fas fa-plus me-1"></i> Novo Departamento
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Tabela --}}
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
            {{-- Toolbar --}}
            <div class="card-header bg-white p-3 border-bottom d-flex justify-content-end align-items-center">
                <form method="GET" action="{{ route('departamentos.index') }}" class="d-flex align-items-center gap-2">
                    <label for="per_page" class="small text-muted fw-medium mb-0">Por página</label>
                    @php($pp = (int) request('per_page', 15))
                    <select id="per_page" name="per_page" class="form-select form-select-sm" style="width: auto;">
                        <option value="10" {{ $pp == 10 ? 'selected' : '' }}>10</option>
                        <option value="15" {{ $pp == 15 ? 'selected' : '' }}>15</option>
                        <option value="25" {{ $pp == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $pp == 50 ? 'selected' : '' }}>50</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 border-bottom text-uppercase small fw-bold text-muted">Departamento</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Sigla</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Gabinete</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Chefe</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departamentos as $departamento)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold flex-shrink-0"
                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, #eff6ff, #e0e7ff); color: #2563eb; border: 1px solid #e2e8f0; font-size: 0.8rem;">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($departamento->sigla ?: $departamento->nome, 0, 2)) }}
                                        </div>
                                        <div class="fw-bold text-dark">{{ $departamento->nome }}</div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-secondary border">{{ $departamento->sigla }}</span></td>
                                <td>
                                    @if ($departamento->gabinete)
                                        <span
                                            class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle rounded-pill">
                                            <i class="fas fa-building-user me-1"></i>{{ $departamento->gabinete->nome }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($departamento->chefe)
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-secondary-subtle text-secondary-emphasis d-flex align-items-center justify-content-center me-2 fw-semibold flex-shrink-0"
                                                style="width: 30px; height: 30px; font-size: 0.75rem;">
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($departamento->chefe->name, 0, 1)) }}
                                            </div>
                                            <span class="text-dark small">{{ $departamento->chefe->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button
                                            class="btn btn-icon btn-sm btn-light rounded-circle shadow-sm dropdown-action-btn"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                            style="width: 32px; height: 32px;">
                                            <i class="fas fa-ellipsis-v text-muted"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                            <li>
                                                <h6 class="dropdown-header text-uppercase small fw-bold">Gerenciar</h6>
                                            </li>
                                            <li><a class="dropdown-item"
                                                    href="{{ route('departamentos.edit', $departamento) }}"><i
                                                        class="fas fa-edit me-2 text-secondary w-20"></i>Editar</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form action="{{ route('departamentos.destroy', $departamento) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Tem certeza que deseja excluir?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i
                                                            class="fas fa-trash-alt me-2 w-20"></i>Excluir</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <div class="bg-light rounded-circle p-4 mb-3">
                                            <i class="fas fa-sitemap fa-3x text-muted opacity-50"></i>
                                        </div>
                                        <h5 class="fw-bold text-muted">Nenhum departamento cadastrado</h5>
                                        <p class="text-muted small mb-3">Comece criando o primeiro departamento.</p>
                                        <a href="{{ route('departamentos.create') }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i> Novo Departamento
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (
                $departamentos instanceof \Illuminate\Contracts\Pagination\Paginator ||
                    $departamentos instanceof \Illuminate\Pagination\LengthAwarePaginator)
                @php($from = ($departamentos->currentPage() - 1) * $departamentos->perPage() + 1)
                @php($to = min($departamentos->currentPage() * $departamentos->perPage(), $departamentos->total()))
                <div
                    class="d-flex flex-column flex-md-row justify-content-between align-items-center p-4 border-top bg-light gap-3">
                    <small class="text-muted">Mostrando {{ $from }}–{{ $to }} de
                        {{ $departamentos->total() }}</small>
                    {{ $departamentos->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit do filtro "Por página"
        document.querySelectorAll('form[action="{{ route('departamentos.index') }}"] select').forEach(el => {
            el.addEventListener('change', () => el.closest('form').submit());
        });

        // Dropdowns de ação com posicionamento fixo (evita corte no overflow da tabela)
        document.querySelectorAll('.dropdown-action-btn').forEach(function(btn) {
            new bootstrap.Dropdown(btn, {
                popperConfig: function(def) {
                    return {
                        ...def,
                        strategy: 'fixed'
                    };
                }
            });
        });
    </script>
@endsection
