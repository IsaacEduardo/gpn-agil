@props(['name' => 'anexos[]', 'id' => 'fileInput', 'multiple' => true, 'enableScanner' => true])

<div class="mb-2 d-flex justify-content-between align-items-center">
    <span class="small fw-bold text-muted">Seleção ou Captura de Arquivo</span>
    
    @if($enableScanner)
        <div class="d-flex align-items-center gap-2">
            <span id="mainScannerBadgeIndicator" class="badge bg-light text-muted border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                <i class="fas fa-circle text-secondary me-1" style="font-size: 0.6rem;"></i> Scanner Offline
            </span>
            <button type="button" id="btnOpenScannerModal" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold disabled" data-bs-toggle="modal" data-bs-target="#webscanModal">
                <i class="fas fa-print me-1"></i> Digitalizar do Scanner
            </button>
        </div>
    @endif
</div>

<div class="file-drop-area border rounded-3 p-4 text-center bg-light position-relative" id="dropZone_{{ $id }}">
    <input type="file" name="{{ $name }}" id="{{ $id }}" {{ $multiple ? 'multiple' : '' }} class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".pdf,image/*">
    <div class="d-flex flex-column align-items-center justify-content-center pointer-events-none">
        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 opacity-50"></i>
        <p class="mb-1 fw-medium text-dark">Arraste arquivos ou clique aqui para selecionar</p>
        <p class="small text-muted mb-0">PDFs ou Imagens (máx. {{ (int) (\App\Http\Requests\StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB / 1024) }} MB por ficheiro)</p>
    </div>
</div>
<ul id="fileList_{{ $id }}" class="list-group list-group-flush mt-3 small"></ul>

@once
    <style>
        .file-drop-area { transition: all 0.2s ease; border: 2px dashed #dee2e6 !important; }
        .file-drop-area:hover, .file-drop-area.dragover { background-color: #e9ecef !important; border-color: var(--bs-primary) !important; }
        .cursor-pointer { cursor: pointer; }
        .pointer-events-none { pointer-events: none; }
    </style>
    <script>
        function initFileUpload(inputId, dropZoneId, listId) {
            const fileInput = document.getElementById(inputId);
            const dropZone = document.getElementById(dropZoneId);
            const fileList = document.getElementById(listId);
            
            if(!fileInput || !dropZone) return;

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

            fileInput.addEventListener('change', function() { updateFileList(this.files, fileList); }, false);
            
            dropZone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files; // Assign dropped files to input
                updateFileList(files, fileList);
            }, false);
        }

        function updateFileList(files, listElement) {
            listElement.innerHTML = '';
            if (!files || files.length === 0) return;

            Array.from(files).forEach(file => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0 py-2';
                const iconClass = file.type.includes('pdf') ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                li.innerHTML = `
                    <div class="d-flex align-items-center text-truncate me-3">
                        <i class="fas ${iconClass} me-2 fs-5"></i>
                        <span class="text-truncate fw-semibold text-dark">${file.name}</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border rounded-pill px-2 py-1">${(file.size/1024).toFixed(1)} KB</span>
                `;
                listElement.appendChild(li);
            });
        }
    </script>
@endonce

<script>
    document.addEventListener('DOMContentLoaded', function() {
        initFileUpload('{{ $id }}', 'dropZone_{{ $id }}', 'fileList_{{ $id }}');
    });
</script>
