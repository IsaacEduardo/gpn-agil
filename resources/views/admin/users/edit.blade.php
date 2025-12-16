@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Editar Usuário</h3>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha (deixe em branco para manter)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Papel</label>
                        <select name="role_id" class="form-select" required id="role_id">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Departamento (principal)</label>
                        <select name="departamento_id" class="form-select" id="departamento_id">
                            <option value="">— Sem departamento —</option>
                            @foreach($departamentos as $dep)
                                <option value="{{ $dep->id }}" {{ old('departamento_id', $user->departamento_id) == $dep->id ? 'selected' : '' }}>{{ $dep->nome }}</option>
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
                        @php($selectedDeps = collect(old('departamentos', $user->departamentos->pluck('id')->all())))
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}" @if($selectedDeps->contains($dep->id)) selected @endif>{{ $dep->nome }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Segure Ctrl (Windows) ou Command (Mac) para selecionar múltiplos.</small>
                </div>

                <div class="mb-3 d-none" id="gabinete-chefiado-wrapper">
                    <label class="form-label">Gabinete chefiado</label>
                    <select name="gabinete_chefiado_id" id="gabinete_chefiado_id" class="form-select @error('gabinete_chefiado_id') is-invalid @enderror">
                        <option value="">— Selecione o Gabinete —</option>
                        @isset($gabinetesElegiveis)
                            @foreach($gabinetesElegiveis as $gab)
                                <option value="{{ $gab->id }}" {{ old('gabinete_chefiado_id', optional($gabineteAtual)->id) == $gab->id ? 'selected' : '' }}>{{ $gab->nome }}</option>
                            @endforeach
                        @endisset
                    </select>
                    @if(isset($gabinetesElegiveis) && $gabinetesElegiveis->count() === 0)
                        <small class="text-muted">Nenhum gabinete elegível. Vincule o usuário a departamentos do gabinete.</small>
                    @endif
                    @error('gabinete_chefiado_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
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
    const gabWrapper = document.getElementById('gabinete-chefiado-wrapper');
    const gabSelect = document.getElementById('gabinete_chefiado_id');

    const CHEFE_DEP_ROLE_ID = {{ optional($roles->firstWhere('name', 'chefe-departamento'))->id ?? 'null' }};
    const CHEFE_GAB_ROLE_ID = {{ optional($roles->firstWhere('name', 'chefe-gabinete'))->id ?? 'null' }};

    function updateDepartamentoRequirement() {
        const isChefeDep = CHEFE_DEP_ROLE_ID && parseInt(roleSelect.value, 10) === CHEFE_DEP_ROLE_ID;
        if (depSelect) depSelect.required = !!isChefeDep;
        if (warning) warning.classList.toggle('d-none', !isChefeDep);
    }

    function updateGabineteChefiadoVisibility() {
        const isChefeGab = CHEFE_GAB_ROLE_ID && parseInt(roleSelect.value, 10) === CHEFE_GAB_ROLE_ID;
        if (gabWrapper) gabWrapper.classList.toggle('d-none', !isChefeGab);
        if (gabSelect) {
            const hasOptions = gabSelect.options.length > 1;
            gabSelect.required = !!isChefeGab && hasOptions;
        }
    }

    roleSelect.addEventListener('change', function () {
        updateDepartamentoRequirement();
        updateGabineteChefiadoVisibility();
    });

    updateDepartamentoRequirement();
    updateGabineteChefiadoVisibility();
});
</script>
@endpush