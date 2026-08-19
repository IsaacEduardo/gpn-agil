<?php

namespace App\Jobs;

use App\Models\DocumentoInterno;
use App\Services\PdfRenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job Assíncrono para Renderização e Geração de PDF em Segundo Plano.
 * Evita o travamento do worker FPM em chamadas HTTP síncronas.
 */
class GerarPdfDocumentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $documentoId,
        public readonly string $caminhoDestino
    ) {
        $this->afterCommit = true;
    }

    public function handle(PdfRenderService $pdfRenderService): void
    {
        $documento = DocumentoInterno::find($this->documentoId);

        if (!$documento) {
            Log::warning("GerarPdfDocumentoJob: DocumentoInterno ID {$this->documentoId} não encontrado.");
            return;
        }

        try {
            $pdfContent = $pdfRenderService->renderPdfContent($documento);

            $disk = config('filesystems.docs_disk', 'public');
            Storage::disk($disk)->put($this->caminhoDestino, $pdfContent);

            Log::info("GerarPdfDocumentoJob: PDF gerado com sucesso para o documento ID {$this->documentoId} em {$this->caminhoDestino}");
        } catch (\Throwable $e) {
            Log::error("GerarPdfDocumentoJob: Falha ao gerar PDF para Documento ID {$this->documentoId}: " . $e->getMessage());
            throw $e;
        }
    }
}
