<?php

namespace App\Services;

use App\Services\Ocr\OcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Comprime com o Ghostscript os PDF pesados que chegam por upload, antes de
 * serem guardados (config documentos.compressao_pdf).
 *
 * É uma rede de segurança: o agente de digitalização 1.2.0 já entrega PDF
 * leve, mas os postos com o agente antigo e os PDF carregados à mão podiam
 * ocupar vários MB por página. Nunca é motivo para o registo falhar: qualquer
 * dúvida deixa o ficheiro exactamente como veio.
 *
 * Não toca em PDF assinados nem cifrados — reescrevê-los invalidava a
 * assinatura ou falhava — e só substitui o ficheiro se o resultado for um PDF
 * válido, com as mesmas páginas e pelo menos 10% menor.
 */
class CompressorPdf
{
    /** Ganho mínimo para valer a pena trocar o ficheiro do utilizador. */
    private const GANHO_MINIMO = 0.10;

    public function __construct(private readonly OcrService $ocr) {}

    /**
     * Comprime o ficheiro temporário do upload no próprio sítio. Devolve true
     * se o substituiu.
     */
    public function comprimirUpload(UploadedFile $ficheiro): bool
    {
        $caminho = $ficheiro->getRealPath();
        if (! $caminho || ! $this->candidato($caminho, (string) $ficheiro->getMimeType())) {
            return false;
        }

        $gs = $this->ocr->resolveGhostscriptBinary();
        if (! $gs) {
            Log::warning('Compressão de PDF: Ghostscript não encontrado; o ficheiro segue sem comprimir.');

            return false;
        }

        $saida = $caminho.'.gs.pdf';
        try {
            if (! $this->executarGhostscript($gs, $caminho, $saida)) {
                return false;
            }

            $antes = filesize($caminho);
            $depois = @filesize($saida) ?: 0;
            if (! $this->resultadoAceitavel($caminho, $saida, $antes, $depois)) {
                return false;
            }

            if (! @copy($saida, $caminho)) {
                Log::warning('Compressão de PDF: não foi possível substituir o ficheiro temporário.');

                return false;
            }
            // O UploadedFile lê o tamanho por stat(): sem isto gravava o antigo.
            clearstatcache(true, $caminho);

            Log::info(sprintf('Compressão de PDF: %d KB -> %d KB.', intdiv($antes, 1024), intdiv($depois, 1024)));

            return true;
        } finally {
            if (file_exists($saida)) {
                @unlink($saida);
            }
        }
    }

    /** PDF acima do limite, não assinado nem cifrado. */
    public function candidato(string $caminho, string $mime): bool
    {
        $config = config('documentos.compressao_pdf');
        if (! ($config['ativa'] ?? false) || $mime !== 'application/pdf') {
            return false;
        }

        $tamanho = @filesize($caminho) ?: 0;
        if ($tamanho <= ((int) ($config['limite_kb'] ?? 1024)) * 1024) {
            return false;
        }

        return ! $this->ficheiroAssinadoOuCifrado($caminho);
    }

    public function assinadoOuCifrado(string $conteudo): bool
    {
        return (bool) preg_match('#/ByteRange\s*\[|/Type\s*/Sig\b|/Encrypt\b#', $conteudo);
    }

    /**
     * Procura as marcas em todo o ficheiro, por blocos: com uploads até 50 MB,
     * ler tudo de uma vez pesava no memory_limit. O dicionário da assinatura
     * pode estar em qualquer ponto, por isso não basta o início ou o fim. Os
     * blocos sobrepõem-se para apanhar uma marca partida entre dois.
     */
    public function ficheiroAssinadoOuCifrado(string $caminho): bool
    {
        $handle = @fopen($caminho, 'rb');
        if ($handle === false) {
            return true; // Sem conseguir ler, não arriscar reescrever.
        }

        try {
            $anterior = '';
            while (! feof($handle)) {
                $bloco = (string) fread($handle, 1024 * 1024);
                if ($this->assinadoOuCifrado($anterior.$bloco)) {
                    return true;
                }
                $anterior = substr($bloco, -64);
            }

            return false;
        } finally {
            fclose($handle);
        }
    }

    protected function executarGhostscript(string $gs, string $entrada, string $saida): bool
    {
        $dpi = max(72, (int) config('documentos.compressao_pdf.resolucao_dpi', 200));

        $processo = new Process([
            $gs,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS=/ebook',
            '-dSAFER', '-dNOPAUSE', '-dBATCH', '-dQUIET',
            '-dDetectDuplicateImages=true',
            // Sem isto o Ghostscript copia os JPEG tal e qual, e são eles o peso.
            '-dPassThroughJPEGImages=false',
            '-dDownsampleColorImages=true', "-dColorImageResolution={$dpi}", '-dColorImageDownsampleThreshold=1.2',
            '-dDownsampleGrayImages=true', "-dGrayImageResolution={$dpi}", '-dGrayImageDownsampleThreshold=1.2',
            // Preto e branco: 300 dpi em CCITT continua a pesar pouco e o OCR agradece.
            '-dDownsampleMonoImages=true', '-dMonoImageResolution=300', '-dMonoImageDownsampleThreshold=1.2',
            '-sOutputFile='.$saida,
            $entrada,
        ]);
        $processo->setTimeout(max(10, (int) config('documentos.compressao_pdf.timeout_segundos', 90)));

        try {
            $processo->run();
        } catch (\Throwable $e) {
            Log::warning('Compressão de PDF: Ghostscript interrompido: '.$e->getMessage());

            return false;
        }

        if (! $processo->isSuccessful()) {
            Log::warning('Compressão de PDF: Ghostscript falhou: '.mb_substr($processo->getErrorOutput(), 0, 200));

            return false;
        }

        return true;
    }

    protected function resultadoAceitavel(string $original, string $comprimido, int $antes, int $depois): bool
    {
        if ($depois <= 0 || $depois > $antes * (1 - self::GANHO_MINIMO)) {
            return false;
        }

        $inicio = (string) @file_get_contents($comprimido, false, null, 0, 5);
        $fim = (string) @file_get_contents($comprimido, false, null, max(0, $depois - 1024));
        if ($inicio !== '%PDF-' || ! str_contains($fim, '%%EOF')) {
            Log::warning('Compressão de PDF: o Ghostscript produziu um ficheiro inválido; mantém-se o original.');

            return false;
        }

        $paginasAntes = $this->contarPaginas($original);
        $paginasDepois = $this->contarPaginas($comprimido);
        if ($paginasAntes !== null && $paginasAntes !== $paginasDepois) {
            Log::warning("Compressão de PDF: páginas diferentes ({$paginasAntes} -> {$paginasDepois}); mantém-se o original.");

            return false;
        }

        return true;
    }

    /** Número de páginas pelo pdfinfo; null se não for possível saber. */
    protected function contarPaginas(string $caminho): ?int
    {
        $pdfinfo = $this->ocr->resolvePdfinfoBinary();
        if (! $pdfinfo) {
            return null;
        }

        try {
            $processo = new Process([$pdfinfo, $caminho]);
            $processo->setTimeout(20);
            $processo->run();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $processo->isSuccessful() || ! preg_match('/^Pages:\s+(\d+)/m', $processo->getOutput(), $m)) {
            return null;
        }

        return (int) $m[1];
    }
}
