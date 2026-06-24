import './bootstrap';

// NOTA: O arquivamento por arrastar-e-soltar é fornecido pelo componente Blade
// <x-archive-dropzone /> (Alpine via CDN, registado inline) — ver
// resources/views/components/archive-dropzone.blade.php. Não é necessário Vite
// para essa funcionalidade. Mantemos este ponto de entrada apenas para o bootstrap
// do projeto (axios/echo), evitando iniciar uma segunda instância do Alpine.
