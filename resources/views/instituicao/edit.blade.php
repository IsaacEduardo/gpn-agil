@extends('layouts.app')

@section('title', 'Configurações da Instituição')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark m-0"><i class="fas fa-university text-primary me-2"></i>Configurações da Instituição</h3>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-4 border-success mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.instituicao.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <!-- Coluna Principal (Dados e Cabeçalhos) -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold text-secondary m-0"><i class="fas fa-info-circle me-2"></i>Dados de Identificação</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Nome Oficial da Instituição</label>
                                <input type="text" name="nome_oficial" class="form-control form-control-lg @error('nome_oficial') is-invalid @enderror" 
                                       value="{{ old('nome_oficial', $dados->nome_oficial) }}" placeholder="Ex: Governo Provincial do Namibe" required>
                                @error('nome_oficial')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Sigla</label>
                                <input type="text" name="sigla" class="form-control form-control-lg @error('sigla') is-invalid @enderror" 
                                       value="{{ old('sigla', $dados->sigla) }}" placeholder="Ex: GPN" required>
                                @error('sigla')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Cidade Sede</label>
                                <input type="text" name="cidade" class="form-control @error('cidade') is-invalid @enderror" 
                                       value="{{ old('cidade', $dados->cidade) }}" placeholder="Ex: Moçâmedes" required>
                                @error('cidade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">NIF</label>
                                <input type="text" name="nif" class="form-control @error('nif') is-invalid @enderror" 
                                       value="{{ old('nif', $dados->nif) }}" placeholder="Ex: 500028912">
                                @error('nif')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Telefone Principal</label>
                                <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror" 
                                       value="{{ old('telefone', $dados->telefone) }}" placeholder="Ex: +244 923 000 000">
                                @error('telefone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">E-mail Institucional</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                   value="{{ old('email', $dados->email) }}" placeholder="Ex: contacto@govprovnamibe.gov.ao">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold text-muted small text-uppercase">Endereço Completo</label>
                            <textarea name="endereco" class="form-control @error('endereco') is-invalid @enderror" rows="2" 
                                      placeholder="Ex: Av. Eduardo dos Santos, Edifício Sede do Governo, Moçâmedes">{{ old('endereco', $dados->endereco) }}</textarea>
                            @error('endereco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold text-secondary m-0"><i class="fas fa-heading me-2"></i>Timbre e Cabeçalhos Dinâmicos</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Cabeçalho Linha 1 (Principal)</label>
                            <input type="text" name="cabecalho_linha1" class="form-control @error('cabecalho_linha1') is-invalid @enderror" 
                                   value="{{ old('cabecalho_linha1', $dados->cabecalho_linha1) }}" placeholder="Ex: REPÚBLICA DE ANGOLA">
                            <small class="text-muted">Geralmente representa o Estado/Nação.</small>
                            @error('cabecalho_linha1')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Cabeçalho Linha 2 (Secundário)</label>
                            <input type="text" name="cabecalho_linha2" class="form-control @error('cabecalho_linha2') is-invalid @enderror" 
                                   value="{{ old('cabecalho_linha2', $dados->cabecalho_linha2) }}" placeholder="Ex: GOVERNO PROVINCIAL DO NAMIBE">
                            <small class="text-muted">Geralmente representa o órgão governamental máximo local.</small>
                            @error('cabecalho_linha2')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Cabeçalho Linha 3 (Detalhamento)</label>
                            <input type="text" name="cabecalho_linha3" class="form-control @error('cabecalho_linha3') is-invalid @enderror" 
                                   value="{{ old('cabecalho_linha3', $dados->cabecalho_linha3) }}" placeholder="Ex: GABINETE DO GOVERNADOR">
                            <small class="text-muted">Opcional. Geralmente deixado em branco para documentos gerais.</small>
                            @error('cabecalho_linha3')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold text-muted small text-uppercase">Rodapé Geral de Texto</label>
                            <textarea name="rodape_texto" class="form-control @error('rodape_texto') is-invalid @enderror" rows="3" 
                                      placeholder="Informações adicionais que vão no rodapé dos documentos. Ex: Telefones, Emails, Endereço fiscal ou site oficial.">{{ old('rodape_texto', $dados->rodape_texto) }}</textarea>
                            @error('rodape_texto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral (Upload de Logos) -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title fw-bold text-secondary m-0"><i class="fas fa-images me-2"></i>Logótipo e Insígnia</h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase d-block mb-3">Insígnia Oficial (Logo)</label>
                            <div class="d-flex justify-content-center align-items-center border rounded p-3 bg-light mb-3" style="height: 150px; overflow: hidden;">
                                <img id="logo-preview" src="{{ $dados->logo_url }}" alt="Insígnia Preview" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="file" name="logo" id="logo-input" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
                            </div>
                            <small class="text-muted d-block mt-2">Formatos aceitos: PNG, JPG, JPEG, SVG. Tamanho máx: 2MB.</small>
                            @error('logo')
                                <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr class="my-4">

                        <div class="mb-0">
                            <label class="form-label fw-bold text-muted small text-uppercase d-block mb-3">Rodapé Estacionário (Papel Timbrado)</label>
                            <div class="d-flex justify-content-center align-items-center border rounded p-3 bg-light mb-3" style="height: 120px; overflow: hidden;">
                                @if($dados->rodape_url)
                                    <img id="rodape-preview" src="{{ $dados->rodape_url }}" alt="Rodapé Preview" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                @else
                                    <div id="rodape-preview-placeholder" class="text-muted small">Nenhuma imagem de rodapé cadastrada</div>
                                    <img id="rodape-preview" class="d-none" alt="Rodapé Preview" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                @endif
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="file" name="rodape_img" id="rodape-input" class="form-control @error('rodape_img') is-invalid @enderror" accept="image/*">
                            </div>
                            <small class="text-muted d-block mt-2">Imagem de rodapé que será usada na impressão em PDF. Tamanho máx: 2MB.</small>
                            @error('rodape_img')
                                <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm bg-light mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-lightbulb text-warning me-2"></i>Ajuda Rápida</h6>
                        <p class="small text-muted mb-0">
                            Estes dados são usados automaticamente para gerar os cabeçalhos de todos os documentos gerados internamente, PDFs de requisições, termos de entrega de viaturas e folhas de despacho.
                        </p>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm"><i class="fas fa-save me-2"></i>Salvar Alterações</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Preview para o logotipo
    const logoInput = document.getElementById('logo-input');
    const logoPreview = document.getElementById('logo-preview');
    
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    logoPreview.src = this.result;
                });
                reader.readAsDataURL(file);
            }
        });
    }

    // Preview para o rodapé
    const rodapeInput = document.getElementById('rodape-input');
    const rodapePreview = document.getElementById('rodape-preview');
    const rodapePlaceholder = document.getElementById('rodape-preview-placeholder');

    if (rodapeInput && rodapePreview) {
        rodapeInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    rodapePreview.src = this.result;
                    rodapePreview.classList.remove('d-none');
                    if (rodapePlaceholder) {
                        rodapePlaceholder.classList.add('d-none');
                    }
                });
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>
@endpush
