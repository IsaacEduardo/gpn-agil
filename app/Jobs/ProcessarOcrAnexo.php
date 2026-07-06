<?php

namespace App\Jobs;

use App\Models\Anexo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ProcessarOcrAnexo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $anexoId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $anexoId)
    {
        $this->anexoId = $anexoId;
        
        // Ensure the job is dispatched only after the active database transaction commits.
        // This prevents a race condition with Redis queue workers.
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $anexo = Anexo::find($this->anexoId);

        if (! $anexo) {
            return;
        }

        $disk = config('filesystems.docs_disk', 'public');
        if (! Storage::disk($disk)->exists($anexo->caminho_arquivo)) {
            Log::warning("OCR: Arquivo não encontrado para Anexo ID {$this->anexoId}");

            return;
        }

        $fullPath = Storage::disk($disk)->path($anexo->caminho_arquivo);
        $mime = $anexo->mime_type;

        try {
            $text = '';

            if (str_starts_with($mime, 'image/')) {
                $text = $this->performOcr($fullPath);
            } elseif ($mime === 'application/pdf') {
                try {
                    // 1. Try native text extraction (Searchable PDF)
                    $parser = new Parser;
                    $pdf = $parser->parseFile($fullPath);
                    $text = $pdf->getText();
                    $text = trim($text);
                } catch (\Throwable $e) {
                    Log::warning("PDF Parser falhou para Anexo ID {$this->anexoId}: ".$e->getMessage());
                }

                // 2. If no text, try OCR (Scanned PDF)
                if (empty($text)) {
                    Log::info("PDF sem texto detectado (possível imagem). Tentando OCR via Tesseract para Anexo ID {$this->anexoId}...");
                    $text = $this->performOcr($fullPath);
                }
            }

            if (! empty($text)) {
                $anexo->texto_extraido = $text;
                $anexo->save();
            }

        } catch (\Throwable $e) {
            Log::error("OCR Falhou para Anexo ID {$this->anexoId}: ".$e->getMessage());
        }
    }

    protected function performOcr(string $path): string
    {
        try {
            $tesseract = new TesseractOCR($path);

            // 1. Configured Path
            $configPath = config('services.ocr.path');
            if ($configPath && file_exists($configPath)) {
                $tesseract->executable($configPath);
            }
            // 2. Common Windows Paths (Fallback)
            elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $commonPaths = [
                    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                    getenv('LOCALAPPDATA').'\\Tesseract-OCR\\tesseract.exe',
                ];

                foreach ($commonPaths as $p) {
                    if (file_exists($p)) {
                        $tesseract->executable($p);
                        break;
                    }
                }
            }
            // 3. Linux/Mac usually is in PATH, so no executable() call needed unless specific.

            return $tesseract->lang('por', 'eng')->run();
        } catch (\Throwable $e) {
            // Re-throw to be caught by handle
            throw $e;
        }
    }
}
