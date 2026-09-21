            <!-- Attachments Card -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2 fs-6">
                        <i class="fas fa-paperclip text-secondary"></i>
                        <span>Arquivos & Anexos</span>
                    </h6>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ $doc->totalDeFicheiros() }}</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <!-- Attachments -->
                        @foreach ($doc->anexos as $an)
                            <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <div class="bg-secondary bg-opacity-10 p-2 rounded me-3 text-secondary">
                                        <i class="fas fa-paperclip"></i>
                                    </div>
                                    <div class="text-truncate">
                                        <div class="fw-semibold text-truncate" title="{{ $an->nome_original }}">{{ $an->nome_original ?? basename($an->caminho_arquivo) }}</div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="small text-muted">{{ number_format(($an->tamanho_bytes ?? 0) / 1024, 1) }} KB</span>
                                            @if($an->isOcrAplicavel())
                                                @if($an->ocr_status === 'CONCLUIDO')
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.75rem;">
                                                        <i class="fas fa-check-circle me-1"></i> OCR Concluído @if($an->ocr_palavras_count > 0) ({{ $an->ocr_palavras_count }} pal.) @endif
                                                    </span>
                                                @elseif($an->ocr_status === 'PROCESSANDO')
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25" style="font-size: 0.75rem;">
                                                        <i class="fas fa-spinner fa-spin me-1"></i> OCR em Processamento
                                                    </span>
                                                @elseif($an->ocr_status === 'FALHA')
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.75rem;" title="{{ $an->ocr_erro }}">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> OCR Falhou
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size: 0.75rem;">
                                                        <i class="fas fa-clock me-1"></i> OCR Pendente
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 ms-2">
                                    @if($an->isOcrAplicavel())
                                        <button type="button" class="btn btn-sm btn-light text-secondary btn-ver-ocr" data-anexo-id="{{ $an->id }}" data-nome="{{ $an->nome_original }}" title="Ver Texto Extraído (OCR)">
                                            <i class="fas fa-file-alt text-success"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('documentos-entradas.anexos.download', [$doc, $an]) }}" target="_blank" class="btn btn-sm btn-light text-primary" title="Baixar Arquivo"><i class="fas fa-download"></i></a>
                                    <form action="{{ route('documentos-entradas.anexos.destroy', [$doc, $an]) }}" method="POST" onsubmit="return confirm('Remover este anexo?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger" title="Excluir Anexo"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                        
                        @if(!$doc->arquivo_caminho && $doc->anexos->count() === 0)
                            <li class="list-group-item p-4 text-center text-muted">
                                Nenhum arquivo anexado.
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
