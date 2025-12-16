@extends('layouts.app')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Departamentos</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Departamentos</h3>
            <a href="{{ route('departamentos.create') }}" class="btn btn-primary">Novo Departamento</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('departamentos.index') }}" class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="per_page" class="form-label">Por página</label>
                        @php($pp = (int) request('per_page', 15))
                        <select id="per_page" name="per_page" class="form-select">
                            <option value="10" {{ $pp == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ $pp == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ $pp == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $pp == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex gap-2 mt-2">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Filtrar</button>
                        <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary"><i
                                class="fas fa-times me-1"></i> Limpar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Sigla</th>
                                <th>Gabinete</th>
                                <th>Chefe</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departamentos as $departamento)
                                <tr>
                                    <td>{{ $departamento->nome }}</td>
                                    <td>{{ $departamento->sigla }}</td>
                                    <td>{{ optional($departamento->gabinete)->nome ?? '—' }}</td>
                                    <td>{{ optional($departamento->chefe)->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('departamentos.edit', $departamento) }}"
                                            class="btn btn-sm btn-outline-secondary">Editar</a>
                                        <form action="{{ route('departamentos.destroy', $departamento) }}" method="POST"
                                            class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Nenhum departamento cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (
                $departamentos instanceof \Illuminate\Contracts\Pagination\Paginator ||
                    $departamentos instanceof \Illuminate\Pagination\LengthAwarePaginator)
                @php($from = ($departamentos->currentPage() - 1) * $departamentos->perPage() + 1)
                @php($to = min($departamentos->currentPage() * $departamentos->perPage(), $departamentos->total()))
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">Mostrando {{ $from }}–{{ $to }} de
                        {{ $departamentos->total() }}</small>
                    {{ $departamentos->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
        <script>
            document.querySelectorAll('form[action="{{ route('departamentos.index') }}"] select').forEach(el => {
                el.addEventListener('change', () => el.closest('form').submit());
            });
        </script>
    </div>
@endsection
