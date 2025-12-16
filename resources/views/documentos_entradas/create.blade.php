@extends('layouts.app')

@section('title', 'Novo Registro de Documento')

@section('content')
    <div class="container">
        <div class="mb-3 d-flex align-items-center justify-content-between">
            <h3>Novo Registro</h3>
            <a href="{{ route('documentos-entradas.index') }}" class="btn btn-secondary">Voltar</a>
        </div>

        <form action="{{ route('documentos-entradas.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="card mb-3">
                <div class="card-header bg-gradient-primary">
                    <div class="section-title"><i class="fas fa-tags me-2"></i>Classificação e Datas</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Espécie</label>
                            <select name="classificacao_especie" class="form-select @error('classificacao_especie') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($especies ?? [] as $opt)
                                    <option value="{{ $opt }}" {{ old('classificacao_especie') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('classificacao_especie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ref. Nº</label>
                            <input type="text" name="classificacao_ref_numero" value="{{ old('classificacao_ref_numero') }}" placeholder="Ex.: 123/2025" maxlength="30" class="form-control @error('classificacao_ref_numero') is-invalid @enderror" />
                            @error('classificacao_ref_numero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data do Documento</label>
                            <input type="date" name="data_documento" value="{{ old('data_documento') }}" class="form-control @error('data_documento') is-invalid @enderror" />
                            @error('data_documento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-gradient-primary">
                    <div class="section-title"><i class="fas fa-file-alt me-2"></i>Conteúdo</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Procedência</label>
                            <input type="text" name="procedencia" value="{{ old('procedencia') }}" placeholder="Origem do documento" class="form-control @error('procedencia') is-invalid @enderror" />
                            @error('procedencia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Assunto</label>
                            <input type="text" name="assunto" value="{{ old('assunto') }}" placeholder="Descreva o assunto brevemente" class="form-control @error('assunto') is-invalid @enderror" required />
                            @error('assunto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Tags (Palavras-chave)</label>
                            <input type="text" name="tags" value="{{ old('tags') }}" placeholder="Ex: urgente, financeiro, 2025 (separadas por vírgula)" class="form-control @error('tags') is-invalid @enderror" />
                            <div class="form-text">Separe as palavras-chave por vírgula.</div>
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-gradient-primary">
                    <div class="section-title"><i class="fas fa-briefcase me-2"></i>Responsável e Arquivos</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Departamento Responsável</label>
                            <select name="departamento_id" class="form-select @error('departamento_id') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($departamentos as $dep)
                                    <option value="{{ $dep->id }}" {{ (old('departamento_id') ?? ($userDepartamentoId ?? '')) == $dep->id ? 'selected' : '' }}>{{ $dep->nome }}</option>
                                @endforeach
                            </select>
                            @error('departamento_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Anexos (PDF/Imagem)</label>
                            <input id="anexosInput" type="file" name="anexos[]" multiple class="form-control @error('anexos.*') is-invalid @enderror" accept=".pdf,image/*" />
                            <div class="form-text">Pode selecionar vários ficheiros.</div>
                            @error('anexos.*')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <ul id="anexosPreview" class="small mt-2"></ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-gradient-primary">
                    <div class="section-title"><i class="fas fa-comment-dots me-2"></i>Observações</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" rows="3" class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes') }}</textarea>
                            @error('observacoes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Guardar</button>
                </div>
            </div>
        </form>
        <script>
            const anexosInput = document.getElementById('anexosInput');
            const anexosPreview = document.getElementById('anexosPreview');
            if (anexosInput && anexosPreview) {
                anexosInput.addEventListener('change', () => {
                    anexosPreview.innerHTML = '';
                    Array.from(anexosInput.files || []).forEach(f => {
                        const li = document.createElement('li');
                        li.textContent = `${f.name} (${(f.size/1024).toFixed(1)} KB)`;
                        anexosPreview.appendChild(li);
                    });
                });
            }
        </script>
    </div>
@endsection
