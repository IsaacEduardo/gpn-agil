@extends('layouts.app')

@section('title', 'Novo Feedback')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-comment-dots me-2"></i>Novo Feedback
        </h5>
    </div>
    
    <div class="card-body">
        <form action="{{ route('feedbacks.store') }}" method="POST">
            @csrf
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Sua opinião é muito importante para melhorarmos nossos serviços. Por favor, compartilhe sua experiência com o departamento de Logística e Patrimônio.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo" value="{{ old('titulo') }}" required>
                    @error('titulo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label for="tipo" class="form-label">Tipo de Feedback <span class="text-danger">*</span></label>
                    <select class="form-select @error('tipo') is-invalid @enderror" id="tipo" name="tipo" required>
                        <option value="">Selecione...</option>
                        <option value="elogio" {{ old('tipo') == 'elogio' ? 'selected' : '' }}>Elogio</option>
                        <option value="sugestao" {{ old('tipo') == 'sugestao' ? 'selected' : '' }}>Sugestão</option>
                        <option value="critica" {{ old('tipo') == 'critica' ? 'selected' : '' }}>Crítica</option>
                    </select>
                    @error('tipo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="comentario" class="form-label">Comentário <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('comentario') is-invalid @enderror" id="comentario" name="comentario" rows="5" required>{{ old('comentario') }}</textarea>
                    @error('comentario')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Avaliação <span class="text-danger">*</span></label>
                    <div class="star-rating">
                        <div class="rating-group">
                            @for($i = 5; $i >= 1; $i--)
                                <input type="radio" class="rating__input" name="avaliacao" id="rating-{{ $i }}" value="{{ $i }}" {{ old('avaliacao') == $i ? 'checked' : '' }} {{ $i == 5 && !old('avaliacao') ? 'checked' : '' }}>
                                <label class="rating__label" for="rating-{{ $i }}">
                                    <i class="rating__icon rating__icon--star fa fa-star"></i>
                                </label>
                            @endfor
                        </div>
                    </div>
                    @error('avaliacao')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label for="modulo" class="form-label">Módulo Relacionado</label>
                    <select class="form-select @error('modulo') is-invalid @enderror" id="modulo" name="modulo">
                        <option value="">Selecione (opcional)</option>
                        <option value="viaturas" {{ old('modulo') == 'viaturas' ? 'selected' : '' }}>Viaturas</option>
                        <option value="requisicoes" {{ old('modulo') == 'requisicoes' ? 'selected' : '' }}>Requisições</option>
                        <option value="termos" {{ old('modulo') == 'termos' ? 'selected' : '' }}>Termos de Entrega</option>
                        <option value="dashboard" {{ old('modulo') == 'dashboard' ? 'selected' : '' }}>Dashboard</option>
                        <option value="outro" {{ old('modulo') == 'outro' ? 'selected' : '' }}>Outro</option>
                    </select>
                    @error('modulo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3" id="outroModuloContainer" style="display: none;">
                <div class="col-md-12">
                    <label for="outro_modulo" class="form-label">Especifique o módulo</label>
                    <input type="text" class="form-control @error('outro_modulo') is-invalid @enderror" id="outro_modulo" name="outro_modulo" value="{{ old('outro_modulo') }}">
                    @error('outro_modulo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="anonimo" name="anonimo" value="1" {{ old('anonimo') ? 'checked' : '' }}>
                        <label class="form-check-label" for="anonimo">
                            Enviar feedback anonimamente
                        </label>
                    </div>
                    <small class="text-muted">Se marcado, seu nome não será exibido junto ao feedback.</small>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('feedbacks.index') }}" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i> Enviar Feedback
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Estilização das estrelas de avaliação
        const starRating = document.querySelector('.star-rating');
        const stars = starRating.querySelectorAll('.rating__input');
        
        stars.forEach(star => {
            star.addEventListener('change', function() {
                const starValue = this.value;
                
                stars.forEach(s => {
                    const label = s.nextElementSibling;
                    const starIcon = label.querySelector('i');
                    
                    if (s.value <= starValue) {
                        starIcon.classList.add('text-warning');
                    } else {
                        starIcon.classList.remove('text-warning');
                    }
                });
            });
        });
        
        // Inicializar as estrelas com a seleção atual
        const checkedStar = document.querySelector('.rating__input:checked');
        if (checkedStar) {
            const starValue = checkedStar.value;
            stars.forEach(s => {
                const label = s.nextElementSibling;
                const starIcon = label.querySelector('i');
                
                if (s.value <= starValue) {
                    starIcon.classList.add('text-warning');
                }
            });
        }
        
        // Mostrar/ocultar campo "Outro módulo"
        const moduloSelect = document.getElementById('modulo');
        const outroModuloContainer = document.getElementById('outroModuloContainer');
        
        function toggleOutroModulo() {
            if (moduloSelect.value === 'outro') {
                outroModuloContainer.style.display = 'block';
            } else {
                outroModuloContainer.style.display = 'none';
            }
        }
        
        moduloSelect.addEventListener('change', toggleOutroModulo);
        
        // Inicializar o estado do campo "Outro módulo"
        toggleOutroModulo();
    });
</script>

<style>
    .rating-group {
        display: inline-flex;
    }
    
    .rating__input {
        position: absolute !important;
        left: -9999px !important;
    }
    
    .rating__label {
        cursor: pointer;
        padding: 0 0.1em;
        font-size: 2rem;
    }
    
    .rating__icon--star {
        color: #ddd;
    }
    
    .rating__input:checked ~ .rating__label .rating__icon--star {
        color: #ddd;
    }
    
    .rating__input:focus ~ .rating__label .rating__icon--star {
        color: #ddd;
    }
    
    .rating-group:hover .rating__label .rating__icon--star {
        color: #ffb400;
    }
    
    .rating__input:hover ~ .rating__label .rating__icon--star {
        color: #ddd;
    }
    
    .text-warning {
        color: #ffb400 !important;
    }
</style>
@endsection