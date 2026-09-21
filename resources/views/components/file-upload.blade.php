@props(['name' => 'anexos[]', 'id' => 'fileInput', 'multiple' => true, 'enableScanner' => true, 'modalId' => 'webscanModal'])

<div class="mb-2 d-flex justify-content-between align-items-center">
    <span class="small fw-bold text-muted">Seleção ou Captura de Arquivo</span>

    @if($enableScanner)
        {{-- Os identificadores levam o sufixo do input para que a mesma página
             possa ter mais do que um campo de anexos sem IDs repetidos. --}}
        <div class="d-flex align-items-center gap-2">
            <span id="mainScannerBadgeIndicator_{{ $id }}" class="badge bg-light text-muted border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                <i class="fas fa-circle text-secondary me-1" style="font-size: 0.6rem;"></i> Scanner Offline
            </span>
            {{-- `disabled` como atributo, não como classe: numa <button> a classe
                 é apenas visual e o modal abriria na mesma com o agente offline. --}}
            <button type="button" id="btnOpenScannerModal_{{ $id }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" disabled>
                <i class="fas fa-print me-1"></i> Digitalizar do Scanner
            </button>
        </div>
    @endif
</div>

<div class="file-drop-area border rounded-3 p-4 text-center bg-light position-relative" id="dropZone_{{ $id }}">
    <input type="file" name="{{ $name }}" id="{{ $id }}" {{ $multiple ? 'multiple' : '' }} class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".pdf,.jpg,.jpeg,.png">
    <div class="d-flex flex-column align-items-center justify-content-center pointer-events-none">
        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 opacity-50"></i>
        <p class="mb-1 fw-medium text-dark">Arraste arquivos ou clique aqui para selecionar</p>
        <p class="small text-muted mb-0">PDFs ou Imagens (máx. {{ (int) (\App\Http\Requests\StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB / 1024) }} MB por ficheiro)</p>
    </div>
</div>
<ul id="fileList_{{ $id }}" class="list-group list-group-flush mt-3 small"></ul>
<div id="fileRejects_{{ $id }}" class="alert alert-warning small py-2 mt-2 mb-0 d-none" role="alert"></div>

@once
    <style>
        .file-drop-area { transition: all 0.2s ease; border: 2px dashed #dee2e6 !important; }
        .file-drop-area:hover, .file-drop-area.dragover { background-color: #e9ecef !important; border-color: var(--bs-primary) !important; }
        .cursor-pointer { cursor: pointer; }
        .pointer-events-none { pointer-events: none; }
    </style>
    <script>
        // Espelho, no cliente, das regras de StoreDocumentoEntradaRequest. É uma
        // conveniência para não se perder o tempo de upload — a validação que
        // decide continua a ser a do servidor, que estas linhas não substituem.
        const EXTENSOES_ACEITES = ['pdf', 'jpg', 'jpeg', 'png'];
        const LIMITE_FICHEIRO_KB = {{ (int) \App\Http\Requests\StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB }};

        // Devolve o motivo da recusa, ou null se o ficheiro serve.
        function motivoDeRecusa(file) {
            const extensao = (file.name.split('.').pop() || '').toLowerCase();

            if (!EXTENSOES_ACEITES.includes(extensao)) {
                return 'deve ser PDF, JPG ou PNG';
            }

            if (file.size > LIMITE_FICHEIRO_KB * 1024) {
                return `excede o limite de ${Math.round(LIMITE_FICHEIRO_KB / 1024)} MB`;
            }

            return null;
        }

        function mostrarRecusas(caixa, recusados) {
            if (!caixa) return;

            if (!recusados.length) {
                caixa.classList.add('d-none');
                caixa.textContent = '';
                return;
            }

            const linhas = recusados.map(r => `${r.nome} — ${r.motivo}`).join('; ');
            caixa.textContent = recusados.length === 1
                ? `Ficheiro não anexado: ${linhas}.`
                : `Ficheiros não anexados: ${linhas}.`;
            caixa.classList.remove('d-none');
        }

        function initFileUpload(inputId, dropZoneId, listId, rejectsId) {
            const fileInput = document.getElementById(inputId);
            const dropZone = document.getElementById(dropZoneId);
            const fileList = document.getElementById(listId);
            const rejectsBox = rejectsId ? document.getElementById(rejectsId) : null;

            if(!fileInput || !dropZone) return;

            // Mantém no input apenas o que o servidor aceitaria e diz o que ficou de fora.
            function filtrarInput(candidatos) {
                const aceites = new DataTransfer();
                const recusados = [];

                Array.from(candidatos || []).forEach(file => {
                    const motivo = motivoDeRecusa(file);
                    if (motivo) {
                        recusados.push({ nome: file.name, motivo });
                    } else {
                        aceites.items.add(file);
                    }
                });

                fileInput.files = aceites.files;
                mostrarRecusas(rejectsBox, recusados);
                updateFileList(fileInput.files, fileList);
            }

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

            fileInput.addEventListener('change', function() { filtrarInput(this.files); }, false);

            dropZone.addEventListener('drop', function(e) {
                // Acrescenta aos anexos já presentes. Substituir apagaria o PDF
                // que o scanner acabou de anexar ao mesmo input.
                // O atributo accept não se aplica ao arrasto: sem este filtro, um
                // .txt largado aqui era listado com tamanho e só recusado depois
                // da submissão, perdendo o upload e o preenchimento do bloco.
                const merged = new DataTransfer();
                Array.from(fileInput.files || []).forEach(file => merged.items.add(file));
                Array.from(e.dataTransfer.files || []).forEach(file => merged.items.add(file));
                filtrarInput(merged.files);
            }, false);
        }

        function updateFileList(files, listElement) {
            listElement.innerHTML = '';
            if (!files || files.length === 0) return;

            Array.from(files).forEach(file => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0 py-2';
                const iconClass = file.type.includes('pdf') ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                const name = document.createElement('span');
                name.className = 'text-truncate fw-semibold text-dark';
                name.textContent = file.name;
                li.innerHTML = `
                    <div class="d-flex align-items-center text-truncate me-3">
                        <i class="fas ${iconClass} me-2 fs-5"></i>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border rounded-pill px-2 py-1">${(file.size/1024).toFixed(1)} KB</span>
                `;
                li.querySelector('div').appendChild(name);
                listElement.appendChild(li);
            });
        }
    </script>
@endonce

<script>
    document.addEventListener('DOMContentLoaded', function() {
        initFileUpload('{{ $id }}', 'dropZone_{{ $id }}', 'fileList_{{ $id }}', 'fileRejects_{{ $id }}');
    });
</script>
