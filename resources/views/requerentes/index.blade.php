@extends('layouts.app')

@section('title', 'Cadastro de Requerentes')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users text-primary me-2"></i>Cadastro de Requerentes</h1>
            <p class="text-muted">Gestão de Pessoas Físicas e Jurídicas cadastradas para atribuição de terras.</p>
        </div>
        <a href="{{ route('requerentes.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-user-plus me-1"></i> Novo Requerente
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('requerentes.index') }}" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Buscar por Nome, NIF/BI, E-mail ou Telefone..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="tipo_pessoa" class="form-select">
                        <option value="">-- Todos os Tipos de Pessoa --</option>
                        <option value="FISICA" {{ request('tipo_pessoa') === 'FISICA' ? 'selected' : '' }}>Pessoa Física</option>
                        <option value="JURIDICA" {{ request('tipo_pessoa') === 'JURIDICA' ? 'selected' : '' }}>Pessoa Jurídica (Empresa)</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nome / Razão Social</th>
                        <th>NIF / BI</th>
                        <th>Tipo</th>
                        <th>Contato</th>
                        <th>Município / Bairro</th>
                        <th>Solicitações</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requerentes as $req)
                        <tr>
                            <td>
                                <strong><a href="{{ route('requerentes.show', $req) }}" class="text-decoration-none text-dark">{{ $req->nome_razao_social }}</a></strong>
                                @if($req->representante_nome)
                                    <br><small class="text-muted"><i class="fas fa-user-tie me-1"></i>Rep: {{ $req->representante_nome }}</small>
                                @endif
                            </td>
                            <td><code>{{ $req->nif_bi }}</code></td>
                            <td>
                                <span class="badge {{ $req->tipo_pessoa === 'JURIDICA' ? 'bg-indigo text-white' : 'bg-info text-dark' }}">
                                    {{ $req->tipo_pessoa === 'JURIDICA' ? 'P. Jurídica' : 'P. Física' }}
                                </span>
                            </td>
                            <td>
                                <div><i class="fas fa-envelope me-1 text-muted"></i>{{ $req->email ?? 'N/A' }}</div>
                                <div><i class="fas fa-phone me-1 text-muted"></i>{{ $req->telefone ?? 'N/A' }}</div>
                            </td>
                            <td>{{ $req->municipio }} {{ $req->bairro ? ' / ' . $req->bairro : '' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $req->solicitacoes_count ?? $req->solicitacoes()->count() }} pedidos</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('requerentes.show', $req) }}" class="btn btn-sm btn-outline-info me-1" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('requerentes.edit', $req) }}" class="btn btn-sm btn-outline-warning me-1" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-users-slash fa-2x mb-2"></i><br>
                                Nenhum requerente cadastrado ou encontrado com os filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requerentes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $requerentes->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
