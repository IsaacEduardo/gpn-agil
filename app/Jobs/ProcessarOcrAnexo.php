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

        // Only process images or PDFs (if converted to images, but Tesseract handles images best)
        // For PDF, we might need a separate PDF parser or convert PDF to Image first.
        // Tesseract wrapper might support PDF if Tesseract binary supports it, but usually it needs image input.
        // For simplicity in this iteration, we focus on Images or rely on Tesseract to handle PDF if configured.
        // Actually, Tesseract natively supports images. For PDF, we often need 'pdftotext' or convert pages to images.
        // However, let's try to run Tesseract on the file.

        // Check if file exists
        $disk = config('filesystems.docs_disk', 'public');
        if (! Storage::disk($disk)->exists($anexo->caminho_arquivo)) {
            Log::warning("OCR: Arquivo não encontrado para Anexo ID {$this->anexoId}");
            return;
        }

        $fullPath = Storage::disk($disk)->path($anexo->caminho_arquivo);
        $mime = $anexo->mime_type;

        try {
            $text = '';
            
            // Basic support for Images
            if (str_starts_with($mime, 'image/')) {
                // Tenta encontrar o executável se não estiver no PATH
                // Ajuste este caminho conforme a instalação do usuário se necessário
                $tesseract = new TesseractOCR($fullPath);
                
                // Fallback comum no Windows se não estiver no PATH
                if (file_exists('C:\\Program Files\\Tesseract-OCR\\tesseract.exe')) {
                    $tesseract->executable('C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
                } elseif (file_exists('C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe')) {
                    $tesseract->executable('C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe');
                }

                 $text = $tesseract
                    ->lang('por', 'eng') // Portuguese and English
                    ->run();
            } elseif ($mime === 'application/pdf') {
                try {
                    // 1. Tentar extrair texto nativo (PDF Pesquisável)
                    $parser = new Parser();
                    $pdf = $parser->parseFile($fullPath);
                    $text = $pdf->getText();
                    
                    // Limpar caracteres estranhos
                    $text = trim($text);
                } catch (\Throwable $e) {
                    Log::warning("PDF Parser falhou para Anexo ID {$this->anexoId}: " . $e->getMessage());
                }

                // 2. Se não encontrou texto, pode ser um PDF escaneado (Imagem)
                // Tenta usar Tesseract no arquivo PDF (pode requerer Ghostscript instalado no servidor)
                if (empty($text)) {
                    Log::info("PDF sem texto detectado (possível imagem). Tentando OCR via Tesseract para Anexo ID {$this->anexoId}...");
                    try {
                        $tesseract = new TesseractOCR($fullPath);
                        
                        // Configura executável se necessário
                        if (file_exists('C:\\Program Files\\Tesseract-OCR\\tesseract.exe')) {
                            $tesseract->executable('C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
                        } elseif (file_exists('C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe')) {
                            $tesseract->executable('C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe');
                        }

                        $text = $tesseract->lang('por', 'eng')->run();
                    } catch (\Throwable $e) {
                        Log::warning("OCR em PDF falhou (provável falta de Ghostscript/ImageMagick): " . $e->getMessage());
                    }
                }
            }

            if (! empty($text)) {
                $anexo->texto_extraido = $text;
                $anexo->save();
            }

        } catch (\Throwable $e) {
            Log::error("OCR Falhou para Anexo ID {$this->anexoId}: " . $e->getMessage());
        }
    }
}
