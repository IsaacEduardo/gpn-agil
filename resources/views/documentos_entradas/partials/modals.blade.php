<!-- Modal Relacionar Documento -->
<div class="modal fade" id="modalRelacionarDocumento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('documentos-entradas.relacionar', $doc) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Vínculo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold">Buscar Documento Relacionado</label>
                        <input type="text" id="search-doc-input" class="form-control" placeholder="Digite o ID, Assunto, Referência ou Conteúdo..." autocomplete="off">
                        <input type="hidden" name="relacionado_id" id="relacionado_id_hidden" required>
                        <input type="hidden" name="relacionado_type" id="relacionado_type_hidden" value="entrada">
                        
                        <div id="search-results" class="list-group position-absolute w-100 shadow-sm d-none bg-white border rounded" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                            <!-- Results will be injected here -->
                        </div>
                        <div id="selected-doc-info" class="form-text text-success d-none mt-2">
                            <i class="fas fa-check-circle"></i> Selecionado: <span id="selected-doc-text" class="fw-bold"></span>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" onclick="clearSelection()" title="Remover seleção"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="form-text mt-1">Pesquise pelo ID, Assunto, Número de Referência ou Conteúdo do documento.</div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const searchInput = document.getElementById('search-doc-input');
                        const resultsContainer = document.getElementById('search-results');
                        const hiddenInput = document.getElementById('relacionado_id_hidden');
                        const hiddenTypeInput = document.getElementById('relacionado_type_hidden');
                        const selectedInfo = document.getElementById('selected-doc-info');
                        const selectedText = document.getElementById('selected-doc-text');
                        let debounceTimer;

                        window.clearSelection = function() {
                            hiddenInput.value = '';
                            hiddenTypeInput.value = 'entrada';
                            selectedInfo.classList.add('d-none');
                            searchInput.value = '';
                            searchInput.focus();
                        };

                        searchInput.addEventListener('input', function() {
                            clearTimeout(debounceTimer);
                            const query = this.value;
                            
                            if (query.length < 2) {
                                resultsContainer.classList.add('d-none');
                                return;
                            }

                            debounceTimer = setTimeout(() => {
                                // Add a loading state if desired
                                resultsContainer.innerHTML = '<div class="list-group-item text-muted"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Pesquisando...</div>';
                                resultsContainer.classList.remove('d-none');

                                fetch(`{{ route('documentos-entradas.search.json') }}?q=${encodeURIComponent(query)}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        resultsContainer.innerHTML = '';
                                        if (data.length > 0) {
                                            data.forEach(doc => {
                                                // Don't show current doc in results
                                                if(doc.type === 'entrada' && doc.id == {{ $doc->id }}) return;

                                                const item = document.createElement('a');
                                                item.href = '#';
                                                item.className = 'list-group-item list-group-item-action';
                                                
                                                // Badge for type
                                                const typeBadge = doc.type === 'interno' 
                                                    ? '<span class="badge bg-info text-dark me-2">INTERNO</span>' 
                                                    : '<span class="badge bg-secondary me-2">ENTRADA</span>';
                                                
                                                item.innerHTML = `<small>${typeBadge} ${doc.text}</small>`;
                                                item.onclick = (e) => {
                                                    e.preventDefault();
                                                    selectDocument(doc);
                                                };
                                                resultsContainer.appendChild(item);
                                            });
                                            if (resultsContainer.children.length > 0) {
                                                resultsContainer.classList.remove('d-none');
                                            } else {
                                                resultsContainer.innerHTML = '<div class="list-group-item text-muted">Nenhum outro documento encontrado</div>';
                                                resultsContainer.classList.remove('d-none');
                                            }
                                        } else {
                                            resultsContainer.innerHTML = '<div class="list-group-item text-muted">Nenhum documento encontrado</div>';
                                            resultsContainer.classList.remove('d-none');
                                        }
                                    })
                                    .catch(err => {
                                        console.error('Error fetching docs:', err);
                                        resultsContainer.innerHTML = '<div class="list-group-item text-danger">Erro na pesquisa. Tente novamente.</div>';
                                    });
                            }, 300);
                        });

                        function selectDocument(doc) {
                            hiddenInput.value = doc.id;
                            hiddenTypeInput.value = doc.type || 'entrada';
                            searchInput.value = ''; 
                            selectedText.textContent = doc.text;
                            selectedInfo.classList.remove('d-none');
                            resultsContainer.classList.add('d-none');
                        }

                        // Hide results when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                                resultsContainer.classList.add('d-none');
                            }
                        });
                    });
                    </script>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de Vínculo</label>
                        <select name="tipo" class="form-select">
                            <option value="relacionado">Relacionado</option>
                            <option value="resposta">Resposta</option>
                            <option value="anexo">Anexo/Apêndice</option>
                            <option value="origem">Origem</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar Vínculo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rejeitar Visto Departamento -->
<div class="modal fade" id="vistoRejeitarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('documentos-entradas.visto.rejeitar', $doc) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Rejeitar visto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-floating">
                        <textarea class="form-control" id="visto_departamento_observacao" name="visto_departamento_observacao" style="height: 120px" placeholder="Motivo da rejeição" required></textarea>
                        <label for="visto_departamento_observacao">Motivo/observação</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rejeitar Visto Gabinete -->
<div class="modal fade" id="vistoGabineteRejeitarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('documentos-entradas.visto-gabinete.rejeitar', $doc) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Rejeitar visto do gabinete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-floating">
                        <textarea class="form-control" id="visto_gabinete_observacao" name="visto_gabinete_observacao" style="height: 120px" placeholder="Motivo da rejeição" required></textarea>
                        <label for="visto_gabinete_observacao">Motivo/observação</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Encaminhar Documento -->
<div class="modal fade" id="modalEncaminharDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Encaminhar documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-encaminhar" action="{{ route('documentos-entradas.encaminhar', $doc) }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Destino (Departamento)</label>
                        {{-- Combobox pesquisável (substitui o <select> de todos os departamentos);
                             exclui o departamento atual do documento das opções. --}}
                        <x-departamento-combobox name="destino_departamento_id" :departamentos="$departamentos"
                            :exclude-id="$doc->departamento_id" />
                        @error('destino_departamento_id')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Despacho / Observação</label>
                        @if(isset($modelosDespacho) && $modelosDespacho->count() > 0)
                            <select class="form-select form-select-sm mb-2 text-primary border-primary" id="modeloDespachoSelect">
                                <option value="">-- Selecionar Modelo de Despacho --</option>
                                @foreach($modelosDespacho as $modelo)
                                    <option value="{{ $modelo->texto }}">{{ $modelo->titulo }}</option>
                                @endforeach
                            </select>
                        @endif
                        <textarea name="observacao" id="observacaoInput" class="form-control" rows="3" placeholder="Insira o despacho aqui...">{{ old('observacao') }}</textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" id="btnEncaminhar" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Encaminhar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Designar Tarefa -->
<div class="modal fade" id="modalDesignarTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Designar tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-designar-tarefa" action="{{ route('documentos-entradas.tarefas.store', $doc) }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Tipo de destino</label>
                        <div class="p-3 bg-light rounded border d-flex gap-3 align-items-center">
                            @php($actor = Auth::user())
                            @php($gab = optional($doc->departamento)->gabinete)
                            @php($actorIsRespGab = $actor && $gab && (int)optional($gab)->responsavel_id === (int)$actor->id)
                            @php($actorIsSuperChefe = $actor && $gab && $actor->isSuperChefeDoGabinete($gab))
                            
                            @if($actorIsRespGab)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoUsuario" value="usuario" checked>
                                    <label class="form-check-label" for="tipoUsuario"><i class="fas fa-user me-1"></i> Usuário Específico</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoDepartamento" value="departamento">
                                    <label class="form-check-label" for="tipoDepartamento"><i class="fas fa-building me-1"></i> Departamento Inteiro</label>
                                </div>
                            @elseif($actorIsSuperChefe)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoUsuario" value="usuario" checked>
                                    <label class="form-check-label" for="tipoUsuario"><i class="fas fa-user me-1"></i> Chefe de Gabinete / Departamento</label>
                                </div>
                            @else
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoUsuario" value="usuario" checked>
                                    <label class="form-check-label" for="tipoUsuario"><i class="fas fa-user me-1"></i> Usuário do Departamento</label>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6" data-field="destino-usuario">
                        <label class="form-label fw-semibold">
                            @if($actorIsSuperChefe)
                                Chefe de destino
                            @else
                                Usuário destino
                            @endif
                            <span class="text-danger">*</span>
                        </label>
                        <select name="destino_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @php($listaUsuarios = ($actorIsRespGab || $actorIsSuperChefe) ? ($gabUsuarios ?? collect()) : ($depUsuarios ?? collect()))
                            @foreach ($listaUsuarios as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($actorIsRespGab)
                        <div class="col-md-6 d-none" data-field="destino-departamento">
                            <label class="form-label fw-semibold">Departamento destino <span class="text-danger">*</span></label>
                            <select class="form-select">
                                <option value="">Selecione um departamento...</option>
                                @foreach ($gabDepartamentos ?? collect() as $d)
                                    <option value="{{ $d->id }}">{{ $d->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Prazo <span class="text-danger">*</span></label>
                        <input type="date" name="prazo_at" class="form-control" value="{{ old('prazo_at') }}" min="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Título da Tarefa <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Analisar solicitação..." required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Descrição Detalhada</label>
                        <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva o que precisa ser feito..."></textarea>
                    </div>
                    <div class="col-12 text-end pt-2 border-top">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSubmitTarefa">
                            <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                            <i class="fas fa-plus me-1"></i> Designar Tarefa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Saída Gabinete -->
<div class="modal fade" id="modalSaidaGabinete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Saída para outro Gabinete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($doc->saida_gabinete_data)
                    <div class="alert alert-info">
                        Documento saiu em {{ optional($doc->saida_gabinete_data)->format('d/m/Y') }} para {{ $doc->encaminhamento_orgao ?? '—' }}.
                    </div>
                @else
                    <form id="form-saida-gabinete" action="{{ route('documentos-entradas.saida-gabinete', $doc) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gabinete de destino</label>
                            <select name="destino_gabinete_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($gabinetes as $gab)
                                    @php($isSame = optional($doc->departamento)->gabinete_id === $gab->id)
                                    <option value="{{ $gab->id }}" {{ $isSame ? 'disabled' : '' }}>
                                        {{ $gab->nome }} @if($gab->sigla) ({{ $gab->sigla }}) @endif
                                        @if($isSame) — atual @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Data da saída</label>
                            <input type="date" name="saida_gabinete_data" class="form-control" value="{{ old('saida_gabinete_data', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ofício Nº</label>
                            <input type="text" name="encaminhamento_oficio_numero" class="form-control" value="{{ old('encaminhamento_oficio_numero') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Despacho / Observação</label>
                            @if(isset($modelosDespacho) && $modelosDespacho->count() > 0)
                                <select class="form-select form-select-sm mb-2 text-primary border-primary" id="modeloDespachoSelectSaida">
                                    <option value="">-- Selecionar Modelo de Despacho --</option>
                                    @foreach($modelosDespacho as $modelo)
                                        <option value="{{ $modelo->texto }}">{{ $modelo->titulo }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <textarea name="observacao" id="observacaoInputSaida" class="form-control" rows="3" placeholder="Insira o despacho aqui...">{{ old('observacao') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-warning small mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i> Após dar saída, encaminhamentos internos serão bloqueados.
                            </div>
                            <button type="button" id="btnSaidaGabinete" class="btn btn-primary"><i class="fas fa-share-square me-1"></i> Dar Saída</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modals -->
<div class="modal fade" id="confirmVistoDepAprovarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar visto do departamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Deseja realmente aprovar o visto para o documento <strong>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" data-action="confirm-visto-dep">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmVistoGabAprovarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar visto do gabinete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Deseja realmente aprovar o visto de gabinete para o documento <strong>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" data-action="confirm-visto-gab">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmReceberEncaminhamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar recebimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><strong>Origem:</strong> <span data-field="rec-origem"></span></div>
                <div class="mb-2"><strong>Destino:</strong> <span data-field="rec-destino"></span></div>
                <div class="mb-2"><strong>Encaminhado em:</strong> <span data-field="rec-data"></span></div>
                <div class="mt-3 text-muted small">Ao confirmar, o documento será marcado como recebido.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="confirm-receber">Confirmar recebimento</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEncaminharModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar encaminhamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><strong>Para:</strong> <span data-field="destino"></span></div>
                <div class="mb-2"><strong>Observação:</strong> <span data-field="observacao"></span></div>
                <div class="mt-3 text-muted small">Ao confirmar, o documento será encaminhado para o destino selecionado.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="confirm">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmSaidaGabineteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar saída</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><strong>Destino:</strong> <span data-field="destino_gabinete"></span></div>
                <div class="mb-2"><strong>Data:</strong> <span data-field="data_saida"></span></div>
                <div class="mb-2"><strong>Ofício:</strong> <span data-field="oficio_numero"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="confirm-saida">Confirmar saída</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Arquivar Documento -->
<div class="modal fade" id="modalArquivarDocumento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('pastas.arquivar', $doc->id) }}" method="POST">
                @csrf
                <input type="hidden" name="tipo" value="entrada">
                <div class="modal-header">
                    <h5 class="modal-title">Arquivar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Selecione a pasta onde deseja arquivar este documento.</p>
                    <div class="mb-3">
                        <label class="form-label">Pasta de Arquivo</label>
                        <select name="pasta_id" class="form-select" required>
                            <option value="auto" selected>✨ Arquivamento Automático (Organização Cronológica)</option>
                            <option value="">-- Ou selecione uma pasta manualmente --</option>
                            @inject('pastaService', 'App\Services\PastaService')
                            @foreach ($pastaService->getFolderTreeOptions(auth()->user()) as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle"></i> O documento será movido para a pasta selecionada e ficará disponível apenas na busca do arquivo.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Arquivar</button>
                </div>
            </form>
        </div>
    </div>
</div>
