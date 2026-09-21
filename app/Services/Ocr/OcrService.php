<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    /**
     * Diagnostic report of the OCR environment and dependencies.
     */
    public function diagnose(): array
    {
        $binaryPath = $this->resolveTesseractBinary();
        $tessdataDir = $this->resolveTessdataDir();
        $binaryExists = $binaryPath !== null && file_exists($binaryPath);

        $availableLangs = [];
        $tesseractVersion = null;
        $tesseractError = null;

        if ($binaryExists) {
            try {
                // Check version
                $versionProcess = new Process([$binaryPath, '--version']);
                $versionProcess->run();
                if ($versionProcess->isSuccessful()) {
                    $firstLine = explode("\n", trim($versionProcess->getOutput()))[0] ?? '';
                    $tesseractVersion = trim($firstLine);
                }

                // Check languages
                $langCmd = [$binaryPath];
                if ($tessdataDir && is_dir($tessdataDir)) {
                    $langCmd[] = '--tessdata-dir';
                    $langCmd[] = $tessdataDir;
                }
                $langCmd[] = '--list-langs';

                $langProcess = new Process($langCmd);
                $langProcess->run();
                if ($langProcess->isSuccessful()) {
                    $lines = explode("\n", trim($langProcess->getOutput()));
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (! empty($line) && ! str_starts_with($line, 'List of') && ! str_contains($line, ':')) {
                            $availableLangs[] = $line;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $tesseractError = $e->getMessage();
            }
        }

        $hasPortuguese = in_array('por', $availableLangs, true) || in_array('por_fast', $availableLangs, true);
        $hasEnglish = in_array('eng', $availableLangs, true);

        // Check auxiliary converters
        $converters = [
            'pdftoppm' => $this->checkBinaryExists($this->resolvePdftoppmBinary() ?? 'pdftoppm'),
            'ghostscript' => $this->checkBinaryExists($this->resolveGhostscriptBinary() ?? (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'gswin64c' : 'gs')),
            'imagemagick' => $this->checkBinaryExists($this->resolveMagickBinary() ?? 'magick'),
            'imagick_extension' => extension_loaded('imagick'),
        ];

        $status = 'OK';
        $warnings = [];

        if (! $binaryExists) {
            $status = 'ERROR';
            $warnings[] = 'Binário do Tesseract OCR não foi localizado no sistema.';
        } elseif (! $hasPortuguese) {
            $status = 'WARN';
            $warnings[] = 'Pacote de língua portuguesa (por.traineddata) não encontrado no tessdata do Tesseract.';
        }

        if (! in_array(true, $converters, true)) {
            $warnings[] = 'Nenhum conversor auxiliar de PDF para imagem (pdftoppm, Ghostscript ou ImageMagick) foi detectado no PATH. PDFs escaneados dependerão do fallback nativo do Tesseract.';
        }

        return [
            'status' => $status,
            'tesseract_binary' => $binaryPath,
            'tesseract_version' => $tesseractVersion,
            'tessdata_dir' => $tessdataDir,
            'languages' => $availableLangs,
            'has_portuguese' => $hasPortuguese,
            'has_english' => $hasEnglish,
            'converters' => $converters,
            'warnings' => $warnings,
            'error' => $tesseractError,
        ];
    }

    /**
     * Process a file (PDF or image) with smart native/scanned fallback.
     *
     * @return array{text: string, method: string, words_count: int}
     */
    public function processFile(string $fullPath, ?string $mime = null): array
    {
        if (! file_exists($fullPath)) {
            throw new \InvalidArgumentException("Arquivo não encontrado no caminho: {$fullPath}");
        }

        $mime = $mime ?: mime_content_type($fullPath) ?: 'application/octet-stream';
        $minWords = (int) config('services.ocr.min_native_words', 50);

        // 1. Image Files
        if (str_starts_with($mime, 'image/')) {
            $rawText = $this->runTesseract($fullPath);
            $cleanText = $this->sanitizeText($rawText);
            $wordsCount = $this->countWords($cleanText);

            return [
                'text' => $cleanText,
                'method' => 'IMAGEM_OCR',
                'words_count' => $wordsCount,
            ];
        }

        // 2. PDF Files (Smart Fallback Pipeline)
        if ($mime === 'application/pdf') {
            // Etapa A: Tentar extração nativa de texto do PDF
            $nativeText = $this->extractNativePdfText($fullPath);
            $cleanNativeText = $this->sanitizeText($nativeText);
            $nativeWordsCount = $this->countWords($cleanNativeText);

            // Se o PDF já contiver texto nativo suficiente (>= 50 palavras), finaliza
            if ($nativeWordsCount >= $minWords) {
                Log::info("OCR: PDF pesquisável identificado com {$nativeWordsCount} palavras. Extração nativa concluída.", [
                    'path' => $fullPath,
                ]);

                return [
                    'text' => $cleanNativeText,
                    'method' => 'PDF_NATIVO',
                    'words_count' => $nativeWordsCount,
                ];
            }

            // Etapa B: PDF escaneado ou com texto insuficiente -> Executar OCR multipágina
            Log::info("OCR: PDF sem texto nativo suficiente ({$nativeWordsCount} palavras). Iniciando pipeline de OCR por imagem...", [
                'path' => $fullPath,
            ]);

            $ocrText = $this->processPdfWithOcr($fullPath);
            $cleanOcrText = $this->sanitizeText($ocrText);

            // Só adotamos o OCR quando ele supera a extração nativa.
            //
            // A condição anterior era `$ocrWordsCount > 0`, que trocava o texto
            // nativo por uma leitura de imagem sempre que o OCR devolvesse
            // alguma coisa. Num despacho curto — texto nativo perfeito, mas
            // abaixo do limiar de palavras — isso substituía o original exacto
            // por um reconhecimento aproximado, e era esse texto degradado que
            // ficava no arquivo e alimentava a pesquisa. O defeito esteve
            // invisível enquanto não havia rasterizador de PDF instalado.
            $ocrWordsCount = $this->countWords($cleanOcrText);

            if ($ocrWordsCount > $nativeWordsCount) {
                return [
                    'text' => $cleanOcrText,
                    'method' => 'TESSERACT_OCR',
                    'words_count' => $ocrWordsCount,
                ];
            }

            if ($nativeWordsCount > 0) {
                Log::info("OCR: extração nativa mantida ({$nativeWordsCount} palavras) por superar o OCR ({$ocrWordsCount}).", [
                    'path' => $fullPath,
                ]);

                return [
                    'text' => $cleanNativeText,
                    'method' => 'PDF_NATIVO',
                    'words_count' => $nativeWordsCount,
                ];
            }

            // Nem nativo nem OCR produziram texto: o documento fica sem camada
            // pesquisável, mas o percurso tem de terminar com estado coerente.
            return [
                'text' => $cleanOcrText,
                'method' => 'TESSERACT_OCR',
                'words_count' => $ocrWordsCount,
            ];
        }

        // 3. Fallback para arquivos de texto simples
        if (in_array($mime, ['text/plain', 'text/csv', 'application/json'])) {
            $content = file_get_contents($fullPath) ?: '';
            $clean = $this->sanitizeText($content);

            return [
                'text' => $clean,
                'method' => 'TEXTO_DIRETO',
                'words_count' => $this->countWords($clean),
            ];
        }

        return [
            'text' => '',
            'method' => 'NAO_SUPORTADO',
            'words_count' => 0,
        ];
    }

    /**
     * Extract text directly from a PDF using Smalot\PdfParser.
     */
    public function extractNativePdfText(string $pdfPath): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($pdfPath);

            return (string) $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning("OCR: Falha ao extrair texto nativo do PDF ({$pdfPath}): ".$e->getMessage());

            return '';
        }
    }

    /**
     * Convert PDF to high-res images (300 DPI) and run Tesseract OCR on each page.
     */
    protected function processPdfWithOcr(string $pdfPath): string
    {
        $tempDir = storage_path('app/temp_ocr/'.Str::uuid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $imageFiles = [];

        try {
            // 1. Try pdftoppm (highest quality and speed)
            $imageFiles = $this->convertWithPdftoppm($pdfPath, $tempDir);

            // 2. Fallback to Ghostscript
            if (empty($imageFiles)) {
                $imageFiles = $this->convertWithGhostscript($pdfPath, $tempDir);
            }

            // 3. Fallback to ImageMagick (binary or extension)
            if (empty($imageFiles)) {
                $imageFiles = $this->convertWithImageMagick($pdfPath, $tempDir);
            }

            // 4. Fallback: Direct Tesseract on PDF if no conversion tool was available
            if (empty($imageFiles)) {
                Log::info("OCR: Nenhum conversor PDF intermediário disponível. Tentando Tesseract direto no PDF...");

                try {
                    return $this->runTesseract($pdfPath);
                } catch (\Throwable $e) {
                    Log::warning("OCR: Tesseract direto no PDF não suportado ou falhou: ".$e->getMessage());

                    return '';
                }
            }

            // Process all page images
            sort($imageFiles);
            $pageTexts = [];
            $totalPages = count($imageFiles);

            foreach ($imageFiles as $index => $imageFile) {
                $pageNum = $index + 1;
                try {
                    $pageText = $this->runTesseract($imageFile);
                    $pageText = trim($pageText);

                    if (! empty($pageText)) {
                        if ($totalPages > 1) {
                            $pageTexts[] = "--- Página {$pageNum} ---\n".$pageText;
                        } else {
                            $pageTexts[] = $pageText;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("OCR: Falha na página {$pageNum} de {$pdfPath}: ".$e->getMessage());
                }
            }

            return implode("\n\n", $pageTexts);

        } finally {
            // Cleanup temporary files and directory
            foreach ($imageFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            if (is_dir($tempDir)) {
                @rmdir($tempDir);
            }
        }
    }

    /**
     * Convert PDF using pdftoppm.
     *
     * @return string[]
     */
    protected function convertWithPdftoppm(string $pdfPath, string $outputDir): array
    {
        $pdftoppm = $this->resolvePdftoppmBinary();
        if (! $pdftoppm) {
            return [];
        }

        $dpi = (int) config('services.ocr.dpi', 300);
        $outputPrefix = $outputDir.DIRECTORY_SEPARATOR.'page';

        $process = new Process([
            $pdftoppm,
            '-png',
            '-r',
            (string) $dpi,
            $pdfPath,
            $outputPrefix,
        ]);
        $process->setTimeout(180);
        $process->run();

        if ($process->isSuccessful()) {
            return glob($outputDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
        }

        return [];
    }

    /**
     * Convert PDF using Ghostscript.
     *
     * @return string[]
     */
    protected function convertWithGhostscript(string $pdfPath, string $outputDir): array
    {
        $gs = $this->resolveGhostscriptBinary();
        if (! $gs) {
            return [];
        }

        $dpi = (int) config('services.ocr.dpi', 300);
        $outputPattern = $outputDir.DIRECTORY_SEPARATOR.'page-%03d.png';

        $process = new Process([
            $gs,
            '-dNOPAUSE',
            '-dBATCH',
            '-sDEVICE=png16m',
            "-r{$dpi}",
            "-sOutputFile={$outputPattern}",
            $pdfPath,
        ]);
        $process->setTimeout(180);
        $process->run();

        if ($process->isSuccessful()) {
            return glob($outputDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
        }

        return [];
    }

    /**
     * Convert PDF using ImageMagick (magick command or PHP Imagick extension).
     *
     * @return string[]
     */
    protected function convertWithImageMagick(string $pdfPath, string $outputDir): array
    {
        $magick = $this->resolveMagickBinary();
        $dpi = (int) config('services.ocr.dpi', 300);

        if ($magick) {
            $outputPattern = $outputDir.DIRECTORY_SEPARATOR.'page-%03d.png';
            $process = new Process([
                $magick,
                '-density',
                (string) $dpi,
                $pdfPath,
                '-alpha',
                'off',
                $outputPattern,
            ]);
            $process->setTimeout(180);
            $process->run();

            if ($process->isSuccessful()) {
                return glob($outputDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
            }
        }

        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick;
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath);

                $files = [];
                $count = 0;
                foreach ($imagick as $page) {
                    $page->setImageFormat('png');
                    $filePath = sprintf('%s%spage-%03d.png', $outputDir, DIRECTORY_SEPARATOR, ++$count);
                    $page->writeImage($filePath);
                    $files[] = $filePath;
                }
                $imagick->clear();
                $imagick->destroy();

                return $files;
            } catch (\Throwable $e) {
                Log::warning('OCR Imagick extension failed: '.$e->getMessage());
            }
        }

        return [];
    }

    /**
     * Execute Tesseract OCR with optimal language and engine flags.
     */
    public function runTesseract(string $imagePath): string
    {
        $tesseract = new TesseractOCR($imagePath);

        // 1. Configure binary executable
        $binary = $this->resolveTesseractBinary();
        if ($binary) {
            $tesseract->executable($binary);
        }

        // 2. Configure custom tessdata directory if available
        $tessdataDir = $this->resolveTessdataDir();
        if ($tessdataDir && is_dir($tessdataDir)) {
            $tesseract->tessdataDir($tessdataDir);
        }

        // 3. Configure optimal languages
        $preferredLangs = explode('+', config('services.ocr.languages', 'por+eng'));
        $preferredLangs = array_filter(array_map('trim', $preferredLangs));
        if (empty($preferredLangs)) {
            $preferredLangs = ['por', 'eng'];
        }

        $tesseract->lang(...$preferredLangs);

        // 4. Configure OCR Engine Mode (LSTM) and Page Segmentation Mode
        $tesseract->oem(1); // LSTM neural network engine
        $tesseract->psm(3); // Fully automatic page segmentation without OSD
        $tesseract->config('preserve_interword_spaces', '1');

        return (string) $tesseract->run();
    }

    /**
     * Sanitize, clean and normalize extracted text to valid UTF-8 without control bytes.
     */
    public function sanitizeText(string $rawText): string
    {
        if (empty($rawText)) {
            return '';
        }

        // 1. Convert/ensure valid UTF-8
        $text = mb_convert_encoding($rawText, 'UTF-8', 'UTF-8');

        // 2. Remove null bytes and non-printable control characters (except newline, tab, carriage return)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // 3. Normalize line endings to \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 4. Remove unicode replacement character
        $text = str_replace("\u{FFFD}", '', $text);

        // 5. Trim trailing whitespace from each line
        $lines = explode("\n", $text);
        $lines = array_map('rtrim', $lines);
        $text = implode("\n", $lines);

        // 6. Limit excessive consecutive blank lines to at most 2
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Count words in a text using unicode word boundary matching.
     */
    public function countWords(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * Resolve the Tesseract executable path.
     */
    public function resolveTesseractBinary(): ?string
    {
        // 1. From configuration
        $configPath = config('services.ocr.path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        // 2. Common Windows paths
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $commonPaths = [
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                'C:/Program Files/Tesseract-OCR/tesseract.exe',
                'C:/Program Files (x86)/Tesseract-OCR/tesseract.exe',
                getenv('LOCALAPPDATA').'\\Tesseract-OCR\\tesseract.exe',
            ];

            foreach ($commonPaths as $path) {
                if ($path && file_exists($path)) {
                    return $path;
                }
            }
        }

        // 3. Search in system PATH
        return $this->findInPath('tesseract');
    }

    /**
     * Resolve the directory containing .traineddata files.
     */
    public function resolveTessdataDir(): ?string
    {
        // 1. Configured custom path
        $customPath = config('services.ocr.tessdata_path');
        if ($customPath && is_dir($customPath)) {
            return $customPath;
        }

        // 2. App storage directory default
        $storagePath = storage_path('app/tessdata');
        if (is_dir($storagePath) && (file_exists($storagePath.'/por.traineddata') || file_exists($storagePath.'/eng.traineddata'))) {
            return $storagePath;
        }

        return null;
    }

    /**
     * Resolve pdftoppm binary path.
     */
    public function resolvePdftoppmBinary(): ?string
    {
        $configPath = config('services.ocr.pdftoppm_path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        return $this->findInPath('pdftoppm');
    }

    /**
     * Resolve Ghostscript binary path.
     */
    public function resolveGhostscriptBinary(): ?string
    {
        $configPath = config('services.ocr.gs_path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $winBinaries = ['gswin64c', 'gswin32c', 'gs'];
            foreach ($winBinaries as $bin) {
                $found = $this->findInPath($bin);
                if ($found) {
                    return $found;
                }
            }

            // Check standard Ghostscript installation directories on Windows
            $gsProgramFiles = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
            if (! empty($gsProgramFiles) && file_exists($gsProgramFiles[0])) {
                return $gsProgramFiles[0];
            }
        }

        return $this->findInPath('gs');
    }

    /**
     * Resolve ImageMagick binary path.
     */
    public function resolveMagickBinary(): ?string
    {
        $configPath = config('services.ocr.magick_path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        return $this->findInPath('magick') ?: $this->findInPath('convert');
    }

    /**
     * Check if a binary can be found in PATH.
     */
    protected function findInPath(string $binary): ?string
    {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWin ? ['where.exe', $binary] : ['which', $binary];

        try {
            $process = new Process($cmd);
            $process->setTimeout(5);
            $process->run();

            if ($process->isSuccessful()) {
                $lines = explode("\n", trim($process->getOutput()));
                $first = trim($lines[0] ?? '');
                if ($first && file_exists($first)) {
                    return $first;
                }
            }
        } catch (\Throwable $e) {
            // Ignora erros de localização de binário
        }

        return null;
    }

    /**
     * Helper to verify if a binary string or path is executable.
     */
    protected function checkBinaryExists(?string $binary): bool
    {
        if (! $binary) {
            return false;
        }

        if (file_exists($binary)) {
            return true;
        }

        return $this->findInPath($binary) !== null;
    }
}
