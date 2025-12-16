@extends('layouts.app')

@section('title', 'Viaturas')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Viaturas</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-car me-2"></i>Viaturas</h5>
            <div>
                <a href="{{ route('viaturas.export.pdf') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                    class="btn btn-danger me-2">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </a>
                <a href="{{ route('viaturas.export.excel') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                    class="btn btn-success me-2">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </a>
                <a href="{{ route('viaturas.create') }}" class="btn btn-light">
                    <i class="fas fa-plus me-1"></i> Nova Viatura
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                    <div class="text-muted small">
                        Mostrando {{ $viaturas->count() }} de {{ $viaturas->total() }} resultados
                    </div>
                    <div class="d-flex gap-2">
                        @php $baseQuery = request()->except('page'); @endphp
                        <a href="{{ route('viaturas.index', array_merge($baseQuery, ['status' => 'Operacional'])) }}"
                            class="btn btn-sm {{ request('status') === 'Operacional' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                            title="Filtrar Operacionais">Operacionais</a>
                        <a href="{{ route('viaturas.index', array_merge($baseQuery, ['status' => 'Em manutenção'])) }}"
                            class="btn btn-sm {{ request('status') === 'Em manutenção' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                            title="Filtrar em manutenção">Em manutenção</a>
                        <a href="{{ route('viaturas.index', array_merge($baseQuery, ['status' => 'Inoperante'])) }}"
                            class="btn btn-sm {{ request('status') === 'Inoperante' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                            title="Filtrar inoperantes">Inoperantes</a>
                    </div>
                </div>
                <form id="viaturas-filter-form" action="{{ route('viaturas.index') }}" method="GET"
                    class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control"
                                placeholder="Buscar por matrícula, modelo, marca..." value="{{ request('search') }}"
                                aria-label="Buscar viaturas">
                            <button class="btn btn-outline-secondary" type="submit" title="Buscar">
                                <i class="fas fa-search"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" title="Limpar busca"
                                id="clear-search-btn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label d-none">Status</label>
                        <select name="status" class="form-select" aria-label="Filtrar por status">
                            <option value="">Todos os Status</option>
                            <option value="Operacional" {{ request('status') == 'Operacional' ? 'selected' : '' }}>
                                Operacional</option>
                            <option value="Em manutenção" {{ request('status') == 'Em manutenção' ? 'selected' : '' }}>Em
                                manutenção</option>
                            <option value="Inoperante" {{ request('status') == 'Inoperante' ? 'selected' : '' }}>Inoperante
                            </option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label d-none">Ano</label>
                        <select name="ano" class="form-select" aria-label="Filtrar por ano">
                            <option value="">Todos os Anos</option>
                            @isset($anos)
                                @foreach ($anos as $ano)
                                    <option value="{{ $ano }}" {{ request('ano') == $ano ? 'selected' : '' }}>
                                        {{ $ano }}</option>
                                @endforeach
                            @endisset
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label d-none">Tipo</label>
                        <select name="tipo" class="form-select" aria-label="Filtrar por tipo">
                            <option value="">Todos os Tipos</option>
                            <option value="Carro" {{ request('tipo') == 'Carro' ? 'selected' : '' }}>Carro</option>
                            <option value="Caminhão" {{ request('tipo') == 'Caminhão' ? 'selected' : '' }}>Caminhão
                            </option>
                            <option value="Moto" {{ request('tipo') == 'Moto' ? 'selected' : '' }}>Moto</option>
                            <option value="Outro" {{ request('tipo') == 'Outro' ? 'selected' : '' }}>Outro</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label d-none">Ordenar por</label>
                        <select name="sort" class="form-select" aria-label="Ordenar por">
                            @php $sort = request('sort', 'created_at'); @endphp
                            <option value="created_at" {{ $sort === 'created_at' ? 'selected' : '' }}>Data de cadastro
                            </option>
                            <option value="placa" {{ $sort === 'placa' ? 'selected' : '' }}>Matrícula</option>
                            <option value="modelo" {{ $sort === 'modelo' ? 'selected' : '' }}>Modelo</option>
                            <option value="marca" {{ $sort === 'marca' ? 'selected' : '' }}>Marca</option>
                            <option value="ano" {{ $sort === 'ano' ? 'selected' : '' }}>Ano</option>
                            <option value="status_operacional" {{ $sort === 'status_operacional' ? 'selected' : '' }}>
                                Status</option>
                            <option value="tipo" {{ $sort === 'tipo' ? 'selected' : '' }}>Tipo</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label d-none">Direção</label>
                        <select name="direction" class="form-select" aria-label="Direção de ordenação">
                            @php $direction = request('direction', 'desc'); @endphp
                            <option value="asc" {{ $direction === 'asc' ? 'selected' : '' }}>Asc</option>
                            <option value="desc" {{ $direction === 'desc' ? 'selected' : '' }}>Desc</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <button type="submit" class="btn btn-primary w-100" title="Aplicar filtros">Filtrar</button>
                    </div>
                    <div class="col-6 col-md-1">
                        <a href="{{ route('viaturas.index') }}" class="btn btn-outline-secondary w-100"
                            title="Limpar filtros">
                            <i class="fas fa-times me-1"></i> Limpar
                        </a>
                    </div>
                </form>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    @php $query = request()->query(); @endphp
                    @if (request('search'))
                        @php
                            $qs = $query;
                            unset($qs['search']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Busca: "{{ request('search') }}" <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                    @if (request('status'))
                        @php
                            $qs = $query;
                            unset($qs['status']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Status: {{ request('status') }} <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                    @if (request('ano'))
                        @php
                            $qs = $query;
                            unset($qs['ano']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Ano: {{ request('ano') }} <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                    @if (request('tipo'))
                        @php
                            $qs = $query;
                            unset($qs['tipo']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Tipo: {{ request('tipo') }} <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                    @if (request('sort'))
                        @php
                            $qs = $query;
                            unset($qs['sort']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Ordenar: {{ request('sort') }} <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                    @if (request('direction'))
                        @php
                            $qs = $query;
                            unset($qs['direction']);
                        @endphp
                        <a href="{{ route('viaturas.index', $qs) }}"
                            class="badge rounded-pill bg-light text-dark border">
                            Direção: {{ request('direction') }} <i class="fas fa-times ms-2"></i>
                        </a>
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover" data-dt="true" data-dt-ordering="false"
                    data-dt-searching="false">
                    <thead>
                        <tr>
                            <th>Matrícula</th>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Ano</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($viaturas as $viatura)
                            <tr>
                                <td>{{ $viatura->placa }}</td>
                                <td>{{ $viatura->modelo }}</td>
                                <td>{{ $viatura->tipo }}</td>
                                <td>{{ $viatura->ano }}</td>
                                <td>
                                    <span
                                        class="badge {{ $viatura->status_operacional == 'Operacional' ? 'bg-success' : ($viatura->status_operacional == 'Em manutenção' ? 'bg-warning' : 'bg-danger') }}">
                                        {{ $viatura->status_operacional }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('viaturas.show', $viatura->id) }}"
                                            class="btn btn-sm btn-info text-white" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('viaturas.edit', $viatura->id) }}"
                                            class="btn btn-sm btn-warning text-white" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $viatura->id }}" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Modal de Exclusão -->
                                    <div class="modal fade" id="deleteModal{{ $viatura->id }}" tabindex="-1"
                                        aria-labelledby="deleteModalLabel{{ $viatura->id }}" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="deleteModalLabel{{ $viatura->id }}">
                                                        Confirmar Exclusão</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Tem certeza que deseja excluir a viatura <strong>{{ $viatura->modelo }}
                                                        ({{ $viatura->placa }})
                                                    </strong>?
                                                    <p class="text-danger mt-2">Esta ação não pode ser desfeita.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancelar</button>
                                                    <form action="{{ route('viaturas.destroy', $viatura->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">Excluir</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                        <h5>Nenhuma viatura encontrada</h5>
                                        <p class="text-muted">Não há viaturas cadastradas ou que correspondam aos filtros
                                            aplicados.</p>
                                        <a href="{{ route('viaturas.create') }}" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus me-1"></i> Cadastrar Nova Viatura
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $viaturas->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('viaturas-filter-form');
                if (!form) return;

                const selects = form.querySelectorAll(
                    'select[name="status"], select[name="ano"], select[name="tipo"], select[name="sort"], select[name="direction"]'
                    );
                selects.forEach(sel => sel.addEventListener('change', () => form.submit()));

                const searchInput = form.querySelector('input[name="search"]');
                let debounceTimer;
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => form.submit(), 500);
                    });
                }

                const clearBtn = document.getElementById('clear-search-btn');
                if (clearBtn && searchInput) {
                    clearBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        form.submit();
                    });
                }
            });
        </script>
    @endsection
@endsection
