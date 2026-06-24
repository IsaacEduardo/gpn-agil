@props(['name' => 'anexos[]', 'id' => 'fileInput', 'multiple' => true])

<div class="file-drop-area border rounded-3 p-4 text-center bg-light position-relative" id="dropZone_{{ $id }}">
    <input type="file" name="{{ $name }}" id="{{ $id }}" {{ $multiple ? 'multiple' : '' }} class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".pdf,image/*">
    <div class="d-flex flex-column align-items-center justify-content-center pointer-events-none">
        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 opacity-50"></i>
        <p class="mb-1 fw-medium text-dark">Arraste arquivos ou clique aqui</p>
        <p class="small text-muted mb-0">PDFs ou Imagens (Máx 10MB)</p>
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
            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0 py-2';
                const iconClass = file.type.includes('pdf') ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                li.innerHTML = `
                    <div class="d-flex align-items-center text-truncate me-3">
                        <i class="fas ${iconClass} me-2"></i>
                        <span class="text-truncate">${file.name}</span>
                    </div>
                    <span class="badge bg-light text-secondary">${(file.size/1024).toFixed(1)} KB</span>
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
