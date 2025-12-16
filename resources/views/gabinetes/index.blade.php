@extends('layouts.app')

@section('breadcrumbs')
<div class="container py-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gabinetes</li>
        </ol>
    </nav>
</div>
@endsection

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Gabinetes</h3>
        <a href="{{ route('gabinetes.create') }}" class="btn btn-primary">Novo Gabinete</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Sigla</th>
                            <th>Responsável</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gabinetes as $gabinete)
                            <tr>
                                <td>{{ $gabinete->nome }}</td>
                                <td>{{ $gabinete->sigla }}</td>
                                <td>{{ optional($gabinete->responsavel)->name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('gabinetes.edit', $gabinete) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                    <form action="{{ route('gabinetes.destroy', $gabinete) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nenhum gabinete cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($gabinetes instanceof \Illuminate\Contracts\Pagination\Paginator || $gabinetes instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer">
            {{ $gabinetes->links() }}
        </div>
        @endif
    </div>
</div>
@endsection