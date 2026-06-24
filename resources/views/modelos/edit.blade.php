@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">Editar Modelo de Documento</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('modelos.update', $modelo) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Modelo</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="{{ $modelo->nome }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="documento_especie_id" class="form-label">Espécie de Documento</label>
                            <select class="form-select" id="documento_especie_id" name="documento_especie_id" required>
                                @foreach($especies as $especie)
                                    <option value="{{ $especie->id }}" {{ $modelo->documento_especie_id == $especie->id ? 'selected' : '' }}>
                                        {{ $especie->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="conteudo" class="form-label">Conteúdo (Template)</label>
                            <div class="form-text mb-2">
                                Variáveis disponíveis: @{{DATA_ATUAL}}, @{{ANO}}, @{{USUARIO_NOME}}, @{{DEPARTAMENTO_NOME}}, @{{DOCUMENTO_ORIGEM_NUMERO}}, @{{DOCUMENTO_ORIGEM_ASSUNTO}}
                            </div>
                            <textarea class="form-control" id="conteudo" name="conteudo" rows="15">{{ $modelo->conteudo }}</textarea>
                        </div>

                        <div class="card bg-light border-0 mb-3" x-data="fieldBuilder({{ json_encode($modelo->campos_dinamicos ?? (object)[]) }})">
                            <div class="card-header bg-transparent border-0 fw-bold pb-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-cubes me-1"></i> Construtor de Campos Dinâmicos</span>
                                <button type="button" class="btn btn-sm btn-primary" @click="addField()">
                                    <i class="fas fa-plus me-1"></i> Adicionar Campo
                                </button>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">Defina campos específicos para este modelo. Eles aparecerão na barra lateral na criação de documentos e serão substituídos no texto por <code>&#123;&#123;NOME_DO_CAMPO&#125;&#125;</code>.</p>
                                
                                <input type="hidden" name="campos_dinamicos_json" :value="JSON.stringify(fieldsAsObject())">

                                <template x-for="(field, index) in fields" :key="index">
                                    <div class="row g-2 align-items-center mb-2 p-2 border rounded bg-white shadow-xs">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" x-model="field.key" placeholder="Nome da Variável (ex: hora_inicio)" @input="sanitizeKey(field)" required>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select form-select-sm" x-model="field.type" required>
                                                <option value="text">Texto Simples</option>
                                                <option value="textarea">Área de Texto (Textarea)</option>
                                                <option value="date">Data (Date)</option>
                                                <option value="number">Número (Number)</option>
                                                <option value="time">Hora (Time)</option>
                                                <option value="select">Seleção (Dropdown)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <div x-show="field.type === 'select'">
                                                <input type="text" class="form-control form-control-sm" x-model="field.options" placeholder="Opções separadas por vírgula (ex: Aprovado, Rejeitado)" :required="field.type === 'select'">
                                            </div>
                                            <div x-show="field.type !== 'select'" class="text-muted small">
                                                Uso no texto: <code x-text="'&#123;&#123;' + field.key.toUpperCase() + '&#125;&#125;'"></code>
                                            </div>
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0" @click="removeField(index)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="fields.length === 0" class="text-center py-3 text-muted small bg-white border rounded">
                                    Nenhum campo dinâmico definido.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" {{ $modelo->ativo ? 'checked' : '' }}>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <a href="{{ route('modelos.index') }}" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- AlpineJS & TinyMCE -->
<script src="//unpkg.com/alpinejs" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: 'textarea#conteudo',
    plugins: 'link lists table',
    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | indent outdent | bullist numlist | table'
  });

  document.addEventListener('alpine:init', () => {
      Alpine.data('fieldBuilder', (initialFields = {}) => ({
          fields: [],
          init() {
              Object.entries(initialFields).forEach(([key, value]) => {
                  let parts = value.split(':');
                  let type = parts[0];
                  let options = parts.slice(1).join(':');
                  this.fields.push({
                      key: key,
                      type: type,
                      options: options || ''
                  });
              });
          },
          addField() {
              this.fields.push({ key: '', type: 'text', options: '' });
          },
          removeField(index) {
              this.fields.splice(index, 1);
          },
          sanitizeKey(field) {
              field.key = field.key.toLowerCase().replace(/[^a-z0-9_]/g, '');
          },
          fieldsAsObject() {
              let obj = {};
              this.fields.forEach(f => {
                  if (f.key.trim() !== '') {
                      let val = f.type;
                      if (f.type === 'select' && f.options.trim() !== '') {
                          val += ':' + f.options.trim();
                      }
                      obj[f.key.trim()] = val;
                  }
              });
              return obj;
          }
      }));
  });
</script>
@endsection
