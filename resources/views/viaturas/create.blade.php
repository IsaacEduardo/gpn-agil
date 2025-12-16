@extends('layouts.app')

@section('title', 'Nova Viatura')

@section('content')
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-car me-2"></i>Cadastrar Nova Viatura</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('viaturas.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Informações Básicas</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3" style="display: none;">
                                    <label for="identificacao" class="form-label">Identificação <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('identificacao') is-invalid @enderror"
                                        id="identificacao" name="identificacao" value="AUTO" required>
                                    @error('identificacao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="placa" class="form-label">Matrícula <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('placa') is-invalid @enderror"
                                        id="placa" name="placa" value="{{ old('placa') }}"
                                        placeholder="Ex.: CA-00-00, NBH-00-00, BEA-00-00-AB"
                                        pattern="^([A-Za-z]{2}-\d{2}-\d{2}(-[A-Za-z]{2})?|[A-Za-z]{3}-\d{2}-\d{2}(-[A-Za-z]{2})?)$"
                                        title="Permite: LL-00-00, LL-00-00-LL, LLL-00-00 ou LLL-00-00-LL" required>
                                    <div class="form-text">Aceita: LL-00-00, LL-00-00-LL, LLL-00-00 ou LLL-00-00-LL
                                        (quaisquer letras).
                                        Ex.: CA-00-00, NBH-00-00, NBH-00-00-AB, BE-00-00, BEA-00-00-AB</div>
                                    @error('placa')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="modelo" class="form-label">Modelo <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('modelo') is-invalid @enderror"
                                        id="modelo" name="modelo" value="{{ old('modelo') }}" required>
                                    @error('modelo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="marca" class="form-label">Marca <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('marca') is-invalid @enderror"
                                        id="marca" name="marca" value="{{ old('marca') }}" required>
                                    @error('marca')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ano" class="form-label">Ano <span
                                                class="text-danger">*</span></label>
                                        <input type="number" class="form-control @error('ano') is-invalid @enderror"
                                            id="ano" name="ano" value="{{ old('ano') }}" min="1900"
                                            max="{{ date('Y') + 1 }}" required>
                                        @error('ano')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="tipo" class="form-label">Tipo <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select @error('tipo') is-invalid @enderror" id="tipo"
                                            name="tipo" required>
                                            <option value="">Selecione...</option>
                                            <option value="Carro" {{ old('tipo') == 'Carro' ? 'selected' : '' }}>Carro
                                            </option>
                                            <option value="Caminhão" {{ old('tipo') == 'Caminhão' ? 'selected' : '' }}>
                                                Caminhão</option>
                                            <option value="Moto" {{ old('tipo') == 'Moto' ? 'selected' : '' }}>Moto
                                            </option>
                                            <option value="Outro" {{ old('tipo') == 'Outro' ? 'selected' : '' }}>Outro
                                            </option>
                                        </select>
                                        @error('tipo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Informações Adicionais</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status_operacional" class="form-label">Status Operacional <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select @error('status_operacional') is-invalid @enderror"
                                        id="status_operacional" name="status_operacional" required>
                                        <option value="">Selecione...</option>
                                        <option value="Operacional"
                                            {{ old('status_operacional') == 'Operacional' ? 'selected' : '' }}>Operacional
                                        </option>
                                        <option value="Em manutenção"
                                            {{ old('status_operacional') == 'Em manutenção' ? 'selected' : '' }}>Em
                                            manutenção</option>
                                        <option value="Inoperante"
                                            {{ old('status_operacional') == 'Inoperante' ? 'selected' : '' }}>Inoperante
                                        </option>
                                    </select>
                                    @error('status_operacional')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="afetacao" class="form-label">Afetação</label>
                                    <input type="text" class="form-control @error('afetacao') is-invalid @enderror"
                                        id="afetacao" name="afetacao" value="{{ old('afetacao') }}"
                                        placeholder="Informe onde a viatura está afetada">
                                    @error('afetacao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="quilometragem" class="form-label">Quilometragem (km)</label>
                                    <input type="number"
                                        class="form-control @error('quilometragem') is-invalid @enderror"
                                        id="quilometragem" name="quilometragem" value="{{ old('quilometragem') }}"
                                        min="0">
                                    @error('quilometragem')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="observacoes" class="form-label">Observações</label>
                                    <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                                        rows="3">{{ old('observacoes') }}</textarea>
                                    @error('observacoes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Fotos e Documentos</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fotos" class="form-label">Fotos da Viatura (opcional)</label>
                                <input type="file" class="form-control @error('fotos.*') is-invalid @enderror"
                                    id="fotos" name="fotos[]" multiple accept="image/*">
                                <div class="form-text">Você pode selecionar múltiplas fotos. Formatos aceitos: JPG, PNG,
                                    GIF. Tamanho máximo: 2MB por arquivo.</div>
                                @error('fotos.*')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="documentos" class="form-label">Documentos (opcional)</label>
                                <input type="file" class="form-control @error('documentos.*') is-invalid @enderror"
                                    id="documentos" name="documentos[]" multiple accept=".pdf,.doc,.docx">
                                <div class="form-text">Você pode selecionar múltiplos documentos. Formatos aceitos: PDF,
                                    DOC, DOCX. Tamanho máximo: 5MB por arquivo.</div>
                                @error('documentos.*')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('viaturas.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salvar Viatura
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
