<?php

namespace App\Jobs;

use App\Models\Anexo;
use App\Services\Ocr\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessarOcrAnexo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $anexoId;

    /**
     * Número máximo de tentativas do job.
     */
    public int $tries = 3;

    /**
     * Tempo limite de execução em segundos.
     */
    public int $timeout = 300;

    /**
     * Intervalo de retentativa exponencial em segundos.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(int $anexoId)
    {
        $this->anexoId = $anexoId;

        // Ensure the job is dispatched only after the active database transaction commits.
        // This prevents race conditions with Redis queue workers.
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(?OcrService $ocrService = null): void
    {
        $ocrService = $ocrService ?: app(OcrService::class);
        $anexo = Anexo::find($this->anexoId);

        if (! $anexo) {
            return;
        }

        // Se o anexo não for PDF nem imagem, marcar como NÃO_APLICÁVEL
        if (! $anexo->isOcrAplicavel()) {
            $anexo->update([
                'ocr_status' => 'NAO_APLICAVEL',
                'ocr_processado_em' => now(),
            ]);

            return;
        }

        $disk = config('filesystems.docs_disk', 'public');
        if (! Storage::disk($disk)->exists($anexo->caminho_arquivo)) {
            Log::warning("OCR: Arquivo não encontrado no disco '{$disk}' para Anexo ID {$this->anexoId}", [
                'caminho' => $anexo->caminho_arquivo,
            ]);

            $anexo->update([
                'ocr_status' => 'FALHA',
                'ocr_erro' => "Arquivo não localizado no disco de armazenamento ({$anexo->caminho_arquivo}).",
                'ocr_processado_em' => now(),
            ]);

            return;
        }

        $fullPath = Storage::disk($disk)->path($anexo->caminho_arquivo);
        $mime = $anexo->mime_type ?: 'application/octet-stream';

        // 1. Atualiza status para PROCESSANDO
        $anexo->update([
            'ocr_status' => 'PROCESSANDO',
            'ocr_tentativas' => ($anexo->ocr_tentativas ?? 0) + 1,
            'ocr_erro' => null,
        ]);

        try {
            // 2. Executa o pipeline de OCR com Smart Fallback e sanitização
            $result = $ocrService->processFile($fullPath, $mime);

            // 3. Persiste o resultado e status CONCLUIDO
            $anexo->update([
                'texto_extraido' => $result['text'] ?: null,
                'ocr_status' => 'CONCLUIDO',
                'ocr_metodo' => $result['method'],
                'ocr_palavras_count' => $result['words_count'],
                'ocr_processado_em' => now(),
                'ocr_erro' => null,
            ]);

            Log::info("OCR: Processamento concluído com sucesso para Anexo ID {$this->anexoId}", [
                'metodo' => $result['method'],
                'palavras' => $result['words_count'],
                'arquivo' => $anexo->nome_original,
            ]);

        } catch (\Throwable $e) {
            Log::error("OCR: Falha ao processar Anexo ID {$this->anexoId}: ".$e->getMessage(), [
                'arquivo' => $anexo->nome_original,
                'caminho' => $fullPath,
                'trace' => $e->getTraceAsString(),
            ]);

            $anexo->update([
                'ocr_status' => 'FALHA',
                'ocr_erro' => Str::limit($e->getMessage(), 1000),
                'ocr_processado_em' => now(),
            ]);
        }
    }
}
