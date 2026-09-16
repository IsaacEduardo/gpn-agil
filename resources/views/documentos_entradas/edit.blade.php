@extends('layouts.app')

@section('title', 'Editar Documento')

@section('content')
    <div class="container">
        <div class="mb-3 d-flex align-items-center justify-content-between">
            <h3>Editar Registro</h3>
            <a href="{{ route('documentos-entradas.index') }}" class="btn btn-secondary">Voltar</a>
        </div>

        <form action="{{ route('documentos-entradas.update', $doc) }}" method="POST" enctype="multipart/form-data"
            class="row g-3">
            @csrf
            @method('PUT')
            <div class="card mb-3">
                <div class="card-header bg-gradient-primary">
                    <div class="section-title"><i class="fas fa-tags me-2"></i>Classificação e Datas</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Espécie</label>
                            <select name="classificacao_especie"
                                class="form-select @error('classificacao_especie') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                @foreach ($especies ?? [] as $opt)
                                    <option value="{{ $opt }}"
                                        {{ old('classificacao_especie', $doc->classificacao_especie) == $opt ? 'selected' : '' }}>
                                        {{ $opt }}</option>
                                @endforeach
                            </select>
                            @error('classificacao_especie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ref. Nº</label>
                            <input type="text" name="classificacao_ref_numero"
                                value="{{ old('classificacao_ref_numero', $doc->classificacao_ref_numero) }}" maxlength="30"
                                class="form-control @error('classificacao_ref_numero') is-invalid @enderror" />
                            @error('classificacao_ref_numero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data do Documento</label>
                            <input type="date" name="data_documento"
                                value="{{ old('data_documento', optional($doc->data_documento)->format('Y-m-d')) }}"
                                class="form-control @error('data_documento') is-invalid @enderror" />
                            @error('data_documento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de Entrada</label>
                            <input type="date" name="data_entrada" max="{{ now()->format('Y-m-d') }}"
                                value="{{ old('data_entrada', optional($doc->data_entrada)->format('Y-m-d')) }}"
                                class="form-control @error('data_entrada') is-invalid @enderror" />
                            <div class="form-text">Data em que o documento deu entrada. Conta para o prazo.</div>
                            @error('data_entrada')
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
                            <label class="form-label fw-bold text-muted small mb-1">Procedência / Origem</label>
                            <x-procedencia-combobox name="procedencia_id" :procedencias="$procedencias ?? null" :selected="old('procedencia_id', $doc->procedencia_id)" />
                            @error('procedencia_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('procedencia')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Assunto</label>
                            <input type="text" name="assunto" value="{{ old('assunto', $doc->assunto) }}"
                                class="form-control @error('assunto') is-invalid @enderror" required />
                            @error('assunto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Tags (Palavras-chave)</label>
                            <input type="text" name="tags" value="{{ old('tags', $tags ?? '') }}"
                                placeholder="Ex: urgente, financeiro, 2025 (separadas por vírgula)"
                                class="form-control @error('tags') is-invalid @enderror" />
                            <div class="form-text">Separe as palavras-chave por vírgula.</div>
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        {{-- Saída de gabinete em leitura: escrevê-la aqui deixava o
                             documento com data de saída mas sem encaminhamento externo,
                             sem status e sem aviso ao gabinete de destino — e bloqueava
                             depois a saída verdadeira. Faz-se pela ação própria. --}}
                        <div class="col-md-12">
                            <label class="form-label">Saída de Gabinete</label>
                            <div class="border rounded px-3 py-2 bg-light">
                                <div class="row g-2 small">
                                    <div class="col-md-4">
                                        <span class="text-muted">Data:</span>
                                        {{ optional($doc->saida_gabinete_data)->format('d/m/Y') ?? '—' }}
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted">Órgão:</span> {{ $doc->encaminhamento_orgao ?: '—' }}
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted">Ofício Nº:</span>
                                        {{ $doc->encaminhamento_oficio_numero ?: '—' }}
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">
                                Regista-se em <strong>Registrar Saída de Gabinete</strong>, no detalhe do documento,
                                para que o gabinete de destino seja notificado e a saída fique na tramitação.
                            </div>
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
                        <div class="col-md-4">
                            <label class="form-label">Departamento Responsável</label>
                            {{-- A regra vem do controller (podeAlterarDepartamento):
                                 é a mesma que o servidor aplica no update. --}}
                            @if ($podeAlterarDepartamento ?? false)
                                <select name="departamento_id"
                                    class="form-select @error('departamento_id') is-invalid @enderror" required>
                                    @foreach ($departamentos as $dep)
                                        <option value="{{ $dep->id }}"
                                            {{ old('departamento_id', $doc->departamento_id) == $dep->id ? 'selected' : '' }}>
                                            {{ $dep->nome }}</option>
                                    @endforeach
                                </select>
                                @error('departamento_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="form-control-plaintext">{{ optional($doc->departamento)->nome ?? '—' }}</div>
                                <div class="form-text">A mudança de setor faz-se por encaminhamento, para ficar registada na tramitação.</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Arquivo principal (PDF/Imagem)</label>
                            @if ($doc->arquivo_caminho)
                                <div class="mb-2"><a href="{{ route('documentos-entradas.arquivo.download', $doc) }}"
                                        target="_blank" class="btn btn-sm btn-outline-primary"><i
                                            class="fas fa-eye me-1"></i> Ver atual</a></div>
                            @endif
                            <input type="file" name="arquivo"
                                class="form-control @error('arquivo') is-invalid @enderror" accept=".pdf,image/*" />
                            @error('arquivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Adicionar anexos (PDF/Imagem)</label>
                            <input id="anexosInputEdit" type="file" name="anexos[]" multiple
                                class="form-control @error('anexos.*') is-invalid @enderror" accept=".pdf,image/*" />
                            <div class="form-text">Pode anexar vários ficheiros.</div>
                            @error('anexos.*')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <ul id="anexosPreviewEdit" class="small mt-2"></ul>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Anexos existentes</label>
                            @php($anexos = $doc->anexos)
                            @if ($anexos && $anexos->count())
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Nome</th>
                                                <th>Tamanho</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($anexos as $i => $an)
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>
                                                        <a href="{{ route('documentos-entradas.anexos.download', [$doc, $an]) }}"
                                                            target="_blank">{{ $an->nome_original ?? basename($an->caminho_arquivo) }}</a>
                                                    </td>
                                                    <td>{{ number_format(($an->tamanho_bytes ?? 0) / 1024, 1) }} KB</td>
                                                    <td class="text-end">
                                                        <form
                                                            action="{{ route('documentos-entradas.anexos.destroy', [$doc, $an]) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Remover este anexo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-outline-danger btn-sm"><i
                                                                    class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted">Nenhum anexo.</div>
                            @endif
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
                            <textarea name="observacoes" rows="3" class="form-control">{{ old('observacoes', $doc->observacoes) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Guardar</button>
                </div>
            </div>
        </form>
        <script>
            const anexosInputEdit = document.getElementById('anexosInputEdit');
            const anexosPreviewEdit = document.getElementById('anexosPreviewEdit');
            if (anexosInputEdit && anexosPreviewEdit) {
                anexosInputEdit.addEventListener('change', () => {
                    anexosPreviewEdit.innerHTML = '';
                    Array.from(anexosInputEdit.files || []).forEach(f => {
                        const li = document.createElement('li');
                        li.textContent = `${f.name} (${(f.size/1024).toFixed(1)} KB)`;
                        anexosPreviewEdit.appendChild(li);
                    });
                });
            }
        </script>
    </div>
@endsection
