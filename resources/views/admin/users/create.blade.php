@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Novo Usuário</h3>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Papel</label>
                        <select name="role_id" class="form-select" required id="role_id">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Departamento (principal)</label>
                        <select name="departamento_id" class="form-select" id="departamento_id">
                            <option value="">— Sem departamento —</option>
                            @foreach($departamentos as $dep)
                                <option value="{{ $dep->id }}" {{ old('departamento_id') == $dep->id ? 'selected' : '' }}>{{ $dep->nome }}</option>
                            @endforeach
                        </select>
                        <div id="chefe-warning" class="alert alert-warning mt-2 d-none">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Definir um novo chefe substituirá o chefe atual do departamento.
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Departamentos (múltiplos)</label>
                    <select name="departamentos[]" class="form-select" multiple>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" @if(collect(old('departamentos', []))->contains($dep->id)) selected @endif>{{ $dep->nome }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Segure Ctrl (Windows) ou Command (Mac) para selecionar múltiplos.</small>
                </div>
                <div class="d-flex justify-content-end">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role_id');
    const depSelect = document.getElementById('departamento_id');
    const warning = document.getElementById('chefe-warning');
    const CHEFE_ROLE_ID = {{ optional($roles->firstWhere('name', 'chefe-departamento'))->id ?? 'null' }};

    function updateDepartamentoRequirement() {
        const isChefe = CHEFE_ROLE_ID && parseInt(roleSelect.value, 10) === CHEFE_ROLE_ID;
        depSelect.required = !!isChefe;
        if (warning) {
            warning.classList.toggle('d-none', !isChefe);
        }
    }

    roleSelect.addEventListener('change', updateDepartamentoRequirement);
    updateDepartamentoRequirement();
});
</script>
@endpush