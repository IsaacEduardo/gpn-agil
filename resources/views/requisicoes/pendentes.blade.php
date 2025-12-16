@extends('layouts.app')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item"><a href="{{ route('requisicoes.index') }}">Requisições</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pendentes de Aprovação</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-clock me-2"></i>Requisições Pendentes de Aprovação</span>
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="alert alert-success" role="alert">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger" role="alert">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form action="{{ route('requisicoes.aprovar_em_massa') }}" method="POST" id="massActionForm">
                            @csrf
                            <div class="mb-3">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Tem certeza que deseja aprovar os itens selecionados?')">
                                    <i class="fas fa-check-double me-1"></i> Aprovar Selecionadas
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                            <th>Código</th>
                                            <th>Tipo</th>
                                            <th>Data</th>
                                            <th>Solicitante</th>
                                            <th>Departamento</th>
                                            <th>Empresa</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($requisicoes as $requisicao)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="requisicoes[]" value="{{ $requisicao->id }}"
                                                        class="form-check-input item-checkbox">
                                                </td>
                                                <td>
                                                    <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="fw-bold text-decoration-none">
                                                        {{ $requisicao->codigo_sequencial }}
                                                    </a>
                                                </td>
                                                <td>
                                                    @php 
                                                        $tipo = $requisicao->tipo;
                                                        $tipoLabel = $tipo instanceof \App\Enums\TipoRequisicao ? $tipo->label() : ucfirst($tipo);
                                                    @endphp
                                                    <span class="badge bg-secondary">{{ $tipoLabel }}</span>
                                                </td>
                                                <td>{{ $requisicao->created_at->format('d/m/Y H:i') }}</td>
                                                <td>{{ $requisicao->usuario->name ?? 'N/A' }}</td>
                                                <td>{{ $requisicao->usuario->departamento->nome ?? 'N/A' }}</td>
                                                <td>{{ $requisicao->empresa_destinataria }}</td>
                                                <td>
                                                    <a href="{{ route('requisicoes.show', $requisicao->id) }}"
                                                        class="btn btn-sm btn-info text-white">
                                                        <i class="fas fa-eye"></i> Analisar
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                                                    <p class="mb-0">Não há requisições pendentes de sua aprovação.</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </form>

                        <div class="d-flex justify-content-center mt-4">
                            {{ $requisicoes->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
@endsection
