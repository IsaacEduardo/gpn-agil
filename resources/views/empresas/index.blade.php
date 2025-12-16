@extends('layouts.app')

@section('breadcrumbs')
<div class="container py-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Empresas</li>
        </ol>
    </nav>
</div>
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                        <span>Empresas</span>
                        <div>
                            <a href="{{ route('empresas.create') }}" class="btn btn-primary">Nova Empresa</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form id="filtrosForm" method="GET" action="{{ route('empresas.index') }}" class="mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label for="q" class="form-label">Buscar</label>
                                    <input type="text" id="q" name="q" value="{{ request('q') }}"
                                        class="form-control" placeholder="Nome, contacto, endereço">
                                </div>
                                <div class="col-md-2">
                                    <label for="per_page" class="form-label">Por página</label>
                                    <select id="per_page" name="per_page" class="form-select">
                                        @php($pp = (int) request('per_page', 10))
                                        <option value="10" {{ $pp == 10 ? 'selected' : '' }}>10</option>
                                        <option value="25" {{ $pp == 25 ? 'selected' : '' }}>25</option>
                                        <option value="50" {{ $pp == 50 ? 'selected' : '' }}>50</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="sort" class="form-label">Ordenar por</label>
                                    @php($sort = request('sort', 'nome'))
                                    <select id="sort" name="sort" class="form-select">
                                        <option value="nome" {{ $sort === 'nome' ? 'selected' : '' }}>Nome</option>
                                        <option value="contacto" {{ $sort === 'contacto' ? 'selected' : '' }}>Contacto
                                        </option>
                                        <option value="endereco" {{ $sort === 'endereco' ? 'selected' : '' }}>Endereço
                                        </option>
                                        <option value="created_at" {{ $sort === 'created_at' ? 'selected' : '' }}>Criada em
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="direction" class="form-label">Direção</label>
                                    @php($dir = request('direction', 'asc'))
                                    <select id="direction" name="direction" class="form-select">
                                        <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Ascendente</option>
                                        <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Descendente</option>
                                    </select>
                                </div>
                                <div class="col-md-12 d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>
                                        Filtrar</button>
                                    <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary"><i
                                            class="fas fa-times me-1"></i> Limpar</a>
                                </div>
                            </div>
                        </form>

                        @php($from = ($empresas->currentPage() - 1) * $empresas->perPage() + 1)
                        @php($to = min($empresas->currentPage() * $empresas->perPage(), $empresas->total()))

                        <div class="table-responsive">
                            <table class="table table-striped" data-dt="true" data-dt-ordering="false" data-dt-searching="false" data-dt-paging="false">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Contacto</th>
                                        <th>Endereço</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($empresas as $empresa)
                                        <tr>
                                            <td>{{ $empresa->nome }}</td>
                                            <td>{{ $empresa->contacto ?? '-' }}</td>
                                            <td>{{ $empresa->endereco ?? '-' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('empresas.show', $empresa) }}"
                                                    class="btn btn-sm btn-info">Ver</a>
                                                <a href="{{ route('empresas.edit', $empresa) }}"
                                                    class="btn btn-sm btn-warning">Editar</a>
                                                <form action="{{ route('empresas.destroy', $empresa) }}" method="POST"
                                                    class="d-inline" onsubmit="return confirm('Excluir esta empresa?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center">Nenhuma empresa encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Mostrando {{ $from }}–{{ $to }} de
                                {{ $empresas->total() }}</small>
                            <div>
                                {{ $empresas->onEachSide(1)->links('pagination::bootstrap-5') }}
                            </div>
                        </div>

                        <script>
                            document.querySelectorAll('#filtrosForm select').forEach(el => {
                                el.addEventListener('change', () => document.getElementById('filtrosForm').submit());
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
