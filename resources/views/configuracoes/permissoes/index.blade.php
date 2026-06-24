@extends('layouts.app')

@section('title', 'Matriz de Permissões')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold text-dark">Gerenciamento de Acessos e Permissões</h2>
        <p class="text-muted">Defina o que cada Departamento ou Perfil pode realizar no sistema.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2 text-primary"></i>Matriz de Acesso</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newRoleModal">
                    <i class="fas fa-plus me-1"></i> Novo Perfil
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newPermissionModal">
                    <i class="fas fa-key me-1"></i> Nova Permissão
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        
        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs nav-justified bg-light px-3 pt-3" id="permissionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="true">
                    <i class="fas fa-cogs me-2"></i>Perfis do Sistema
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="department-tab" data-bs-toggle="tab" data-bs-target="#department" type="button" role="tab" aria-controls="department" aria-selected="false">
                    <i class="fas fa-building me-2"></i>Departamentos
                </button>
            </li>
        </ul>

        <div class="tab-content" id="permissionTabsContent">
            <!-- TAB: Perfis do Sistema -->
            <div class="tab-pane fade show active" id="system" role="tabpanel" aria-labelledby="system-tab">
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="bg-light sticky-top" style="z-index: 10; top: 0;">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase small fw-bold text-muted bg-light" style="width: 300px; position: sticky; left: 0; z-index: 11;">Permissão / Ação</th>
                                @foreach($systemRoles as $role)
                                    <th class="text-center py-3 text-uppercase small fw-bold text-primary" style="min-width: 150px;">
                                        {{ $role->name }}
                                        <small class="d-block text-muted fw-normal" style="font-size: 0.65rem;">ID: {{ $role->id }}</small>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permissions as $module => $modulePermissions)
                                <tr class="bg-light">
                                    <td colspan="{{ $systemRoles->count() + 1 }}" class="fw-bold text-dark ps-4 py-2 text-uppercase bg-light" style="font-size: 0.85rem; letter-spacing: 0.5px; position: sticky; left: 0;">
                                        <i class="fas fa-layer-group me-2 text-secondary"></i> {{ $module }}
                                    </td>
                                </tr>
                                @foreach($modulePermissions as $permission)
                                    <tr>
                                        <td class="ps-4 fw-medium text-dark border-end-0 bg-white" style="position: sticky; left: 0;">
                                            <div class="d-flex flex-column">
                                                <span>{{ explode('.', $permission->name)[1] ?? $permission->name }}</span>
                                                <span class="text-muted small fw-normal" style="font-size: 0.75rem;">{{ $permission->name }}</span>
                                            </div>
                                        </td>
                                        @foreach($systemRoles as $role)
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input permission-switch" type="checkbox" 
                                                        data-role-id="{{ $role->id }}" 
                                                        data-permission="{{ $permission->name }}"
                                                        {{ $role->permissions->contains('id', $permission->id) ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ $systemRoles->count() + 1 }}" class="text-center py-5 text-muted">
                                        Nenhuma permissão cadastrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Departamentos -->
            <div class="tab-pane fade" id="department" role="tabpanel" aria-labelledby="department-tab">
                <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="bg-light sticky-top" style="z-index: 10; top: 0;">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase small fw-bold text-muted bg-light" style="width: 300px; position: sticky; left: 0; z-index: 11;">Permissão / Ação</th>
                                @foreach($departmentRoles as $role)
                                    <th class="text-center py-3 text-uppercase small fw-bold text-success" style="min-width: 200px;">
                                        {{ $role->name }}
                                        <small class="d-block text-muted fw-normal" style="font-size: 0.65rem;">ID: {{ $role->id }}</small>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permissions as $module => $modulePermissions)
                                <tr class="bg-light">
                                    <td colspan="{{ $departmentRoles->count() + 1 }}" class="fw-bold text-dark ps-4 py-2 text-uppercase bg-light" style="font-size: 0.85rem; letter-spacing: 0.5px; position: sticky; left: 0;">
                                        <i class="fas fa-layer-group me-2 text-secondary"></i> {{ $module }}
                                    </td>
                                </tr>
                                @foreach($modulePermissions as $permission)
                                    <tr>
                                        <td class="ps-4 fw-medium text-dark border-end-0 bg-white" style="position: sticky; left: 0;">
                                            <div class="d-flex flex-column">
                                                <span>{{ explode('.', $permission->name)[1] ?? $permission->name }}</span>
                                                <span class="text-muted small fw-normal" style="font-size: 0.75rem;">{{ $permission->name }}</span>
                                            </div>
                                        </td>
                                        @foreach($departmentRoles as $role)
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input permission-switch" type="checkbox" 
                                                        data-role-id="{{ $role->id }}" 
                                                        data-permission="{{ $permission->name }}"
                                                        {{ $role->permissions->contains('id', $permission->id) ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ $departmentRoles->count() + 1 }}" class="text-center py-5 text-muted">
                                        Nenhuma permissão cadastrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Nova Role -->
<div class="modal fade" id="newRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('configuracoes.permissoes.role.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Perfil / Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do Perfil</label>
                        <input type="text" name="name" class="form-control" placeholder="Ex: Financeiro" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nova Permissão -->
<div class="modal fade" id="newPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('configuracoes.permissoes.permission.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Permissão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Permissão (Código)</label>
                        <input type="text" name="name" class="form-control" placeholder="Ex: requisicao.aprovar" required>
                        <small class="text-muted">Use um padrão como: <code>modulo.acao</code></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const switches = document.querySelectorAll('.permission-switch');
        
        switches.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const roleId = this.dataset.roleId;
                const permission = this.dataset.permission;
                const isChecked = this.checked;
                
                // Show loading state (optional)
                this.disabled = true;
                
                fetch('{{ route("configuracoes.permissoes.toggle") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        role_id: roleId,
                        permission: permission,
                        attach: isChecked
                    })
                })
                .then(response => response.json())
                .then(data => {
                    this.disabled = false;
                    if(data.success) {
                        // Success
                    } else {
                        alert('Erro ao atualizar permissão');
                        this.checked = !isChecked; // Revert
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    this.disabled = false;
                    this.checked = !isChecked; // Revert
                    alert('Erro de conexão');
                });
            });
        });
    });
</script>
@endsection
