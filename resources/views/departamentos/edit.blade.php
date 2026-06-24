@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <h3>Editar Departamento</h3>
        <a href="{{ route('departamentos.index') }}" class="btn btn-secondary">Voltar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('departamentos.update', $departamento) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="deptTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="dados-tab" data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab" aria-controls="dados" aria-selected="true">
                            <i class="fas fa-info-circle me-2"></i>Dados Gerais
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="permissoes-tab" data-bs-toggle="tab" data-bs-target="#permissoes" type="button" role="tab" aria-controls="permissoes" aria-selected="false">
                            <i class="fas fa-lock me-2"></i>Permissões de Acesso
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="deptTabsContent">
                    <!-- Aba Dados Gerais -->
                    <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $departamento->nome) }}" required>
                            @error('nome')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="sigla" class="form-label">Sigla</label>
                            <input type="text" name="sigla" id="sigla" class="form-control @error('sigla') is-invalid @enderror" value="{{ old('sigla', $departamento->sigla) }}" maxlength="10">
                            @error('sigla')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="gabinete_id" class="form-label">Gabinete</label>
                            <select name="gabinete_id" id="gabinete_id" class="form-select @error('gabinete_id') is-invalid @enderror" required>
                                <option value="">— Selecione —</option>
                                @foreach($gabinetes as $gabinete)
                                    <option value="{{ $gabinete->id }}" {{ old('gabinete_id', $departamento->gabinete_id) == $gabinete->id ? 'selected' : '' }}>{{ $gabinete->nome }}</option>
                                @endforeach
                            </select>
                            @error('gabinete_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="chefe_user_id" class="form-label">Chefe de Departamento</label>
                            <select name="chefe_user_id" id="chefe_user_id" class="form-select @error('chefe_user_id') is-invalid @enderror">
                                <option value="">— Sem chefe —</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->id }}" {{ old('chefe_user_id', optional($chefeAtual)->id) == $usuario->id ? 'selected' : '' }}>
                                        {{ $usuario->name }}
                                        @if($usuario->role && $usuario->role->name === 'chefe-departamento')
                                            (Atual)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('chefe_user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Usuários com papel "user" e o chefe atual são listados; contas admin não podem ser definidas como chefe.</small>
                        </div>
                    </div>

                    <!-- Aba Permissões -->
                    <div class="tab-pane fade" id="permissoes" role="tabpanel" aria-labelledby="permissoes-tab">
                        <div class="alert alert-info border-0 shadow-sm">
                            <i class="fas fa-shield-alt me-2"></i>
                            Defina o que os usuários do departamento <strong>{{ $departamento->nome }}</strong> podem fazer no sistema.
                        </div>
                        
                        <div class="row g-3">
                            @if(isset($permissions) && $permissions->count() > 0)
                                @foreach($permissions as $permission)
                                    <div class="col-md-4 col-lg-3">
                                        <div class="form-check form-switch p-3 border rounded bg-light h-100 d-flex align-items-center">
                                            <input class="form-check-input me-2" type="checkbox" 
                                                name="permissions[]" 
                                                value="{{ $permission->name }}" 
                                                id="perm_{{ $permission->id }}"
                                                {{ (isset($role) && $role->hasPermissionTo($permission->name)) ? 'checked' : '' }}>
                                            <label class="form-check-label w-100 cursor-pointer" for="perm_{{ $permission->id }}">
                                                <span class="fw-medium text-dark">{{ $permission->name }}</span>
                                                <small class="d-block text-muted" style="font-size: 0.75rem;">{{ $permission->guard_name }}</small>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-12 text-center py-4 text-muted">
                                    Nenhuma permissão cadastrada no sistema.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Salvar Alterações</button>
                    <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection