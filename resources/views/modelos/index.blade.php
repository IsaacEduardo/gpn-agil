@extends('layouts.app')

@section('title', __('Modelos de Documentos'))

@section('content')
<div class="container-fluid py-4" x-data="modeloManager()">
    {{-- Header & Toolbar --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-primary mb-1">
                <i class="fas fa-file-contract me-2"></i>{{ __('Modelos de Documentos') }}
            </h4>
            <p class="text-muted small mb-0">{{ __('Gerencie os modelos padrão para criação de documentos.') }}</p>
        </div>
        
        <div class="d-flex gap-2 align-items-center">
            {{-- View Switcher --}}
            <div class="btn-group shadow-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" :class="{ 'active': view === 'list' }" @click="setView('list')" title="{{ __('Lista') }}">
                    <i class="fas fa-list"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" :class="{ 'active': view === 'grid' }" @click="setView('grid')" title="{{ __('Grade') }}">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>

            {{-- New Model Button --}}
            <a href="{{ route('modelos.create') }}" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus me-1"></i> {{ __('Novo Modelo') }}
            </a>
        </div>
    </div>

    {{-- Filters Bar --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('modelos.index') }}" method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="view" :value="view">
                
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">{{ __('Buscar') }}</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('Nome do modelo ou espécie...') }}" value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">{{ __('Status') }}</label>
                    <select name="ativo" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('Todos') }}</option>
                        <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>{{ __('Ativos') }}</option>
                        <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>{{ __('Inativos') }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">{{ __('Ordenar por') }}</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="nome" {{ request('sort') === 'nome' ? 'selected' : '' }}>{{ __('Nome (A-Z)') }}</option>
                        <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>{{ __('Data de Criação') }}</option>
                        <option value="updated_at" {{ request('sort') === 'updated_at' ? 'selected' : '' }}>{{ __('Última Atualização') }}</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                     <button type="submit" class="btn btn-secondary w-100">{{ __('Filtrar') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- LIST VIEW --}}
    <div x-show="view === 'list'" class="fade-in">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">{{ __('Modelo') }}</th>
                            <th>{{ __('Espécie') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Última Alteração') }}</th>
                            <th class="text-end pe-4">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($modelos as $modelo)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $modelo->nome }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">{{ $modelo->especie->nome }}</span>
                                </td>
                                <td>
                                    @if($modelo->ativo)
                                        <span class="badge bg-success-subtle text-success rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i>{{ __('Ativo') }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                                            <i class="fas fa-times-circle me-1"></i>{{ __('Inativo') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    {{ $modelo->updated_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="{{ route('modelos.edit', $modelo) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Editar') }}">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="{{ __('Excluir') }}" onclick="confirmDelete('{{ $modelo->id }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <form id="delete-form-{{ $modelo->id }}" action="{{ route('modelos.destroy', $modelo) }}" method="POST" class="d-none">
                                        @csrf @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-file-contract fa-2x mb-3 opacity-50"></i>
                                    <p>{{ __('Nenhum modelo encontrado.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($modelos->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $modelos->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- GRID VIEW --}}
    <div x-show="view === 'grid'" class="fade-in" style="display: none;">
        <div class="row g-3">
            @forelse($modelos as $modelo)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-3 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-light text-primary border border-primary-subtle">
                                    {{ $modelo->especie->nome }}
                                </span>
                                @if($modelo->ativo)
                                    <span class="text-success" title="{{ __('Ativo') }}"><i class="fas fa-check-circle"></i></span>
                                @else
                                    <span class="text-secondary" title="{{ __('Inativo') }}"><i class="fas fa-ban"></i></span>
                                @endif
                            </div>
                            
                            <h6 class="fw-bold text-dark mb-2">{{ Str::limit($modelo->nome, 50) }}</h6>
                            <p class="text-muted small flex-grow-1 mb-3">
                                {{ Str::limit(strip_tags($modelo->conteudo), 100) }}
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-light">
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <i class="far fa-clock me-1"></i> {{ $modelo->updated_at->format('d/m/Y') }}
                                </small>
                                
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-light text-primary" title="{{ __('Visualizar') }}" @click="openPreview({{ $modelo->id }})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="{{ route('modelos.edit', $modelo) }}" class="btn btn-sm btn-light text-secondary" title="{{ __('Editar') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-light text-danger" title="{{ __('Excluir') }}" onclick="confirmDelete('{{ $modelo->id }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-muted">
                    <p>{{ __('Nenhum modelo encontrado.') }}</p>
                </div>
            @endforelse
        </div>
        @if($modelos->hasPages())
            <div class="mt-4">
                {{ $modelos->links() }}
            </div>
        @endif
    </div>
    
    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-primary" x-text="previewData.nome"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-5 shadow-sm" x-html="previewData.conteudo" style="background: white; min-height: 400px; font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; overflow-y: auto;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Fechar</button>
                    <a :href="'/modelos/' + previewData.id + '/edit'" class="btn btn-primary" x-show="previewData.id">
                        <i class="fas fa-edit me-1"></i> Editar Modelo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@section('styles')
<style>
    .fade-in { animation: fadeIn 0.3s ease-in; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .hover-lift { transition: transform 0.2s, box-shadow 0.2s; }
    .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    
    .bg-success-subtle { background-color: #d1e7dd!important; }
    .bg-secondary-subtle { background-color: #e2e3e5!important; }
</style>
<script src="//unpkg.com/alpinejs" defer></script>
@endsection

@section('scripts')
<script>
    function confirmDelete(id) {
        if(confirm('{{ __("Tem certeza que deseja excluir este modelo?") }}')) {
            document.getElementById('delete-form-' + id).submit();
        }
    }

    document.addEventListener('alpine:init', () => {
        Alpine.data('modeloManager', () => ({
            view: localStorage.getItem('modelo_view') || 'list',
            previewData: {},
            modal: null,

            init() {
                this.modal = new bootstrap.Modal(document.getElementById('previewModal'));
            },
            
            setView(newView) {
                this.view = newView;
                localStorage.setItem('modelo_view', newView);
            },

            openPreview(id) {
                this.previewData = { nome: '{{ __("Carregando...") }}', conteudo: '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>' };
                this.modal.show();

                fetch(`/modelos/${id}`)
                    .then(res => res.json())
                    .then(data => {
                        this.previewData = data;
                    })
                    .catch(err => {
                        this.previewData = { nome: '{{ __("Erro") }}', conteudo: '<p class="text-danger text-center">{{ __("Não foi possível carregar o modelo.") }}</p>' };
                    });
            }
        }));
    });
</script>
@endsection
@endsection
