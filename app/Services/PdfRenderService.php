<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfRenderService
{
    protected ?string $gotenbergUrl;

    public function __construct()
    {
        $this->gotenbergUrl = env('GOTENBERG_URL');
    }

    /**
     * Converte uma string HTML em conteúdo binário PDF.
     * Tenta utilizar o Gotenberg (Headless Chromium) prioritariamente.
     * Caso o Gotenberg esteja indisponível ou inacessível, realiza fallback gracioso para o Dompdf.
     */
    public function renderHtmlToPdf(string $html, array $options = []): string
    {
        if (! empty($this->gotenbergUrl)) {
            try {
                $pdfContent = $this->renderWithGotenberg($html, $options);
                if (! empty($pdfContent)) {
                    return $pdfContent;
                }
            } catch (\Throwable $e) {
                Log::warning('Gotenberg PDF Service indisponível ou com falha. Executando fallback para Dompdf.', [
                    'url' => $this->gotenbergUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->renderWithDompdf($html, $options);
    }

    /**
     * Retorna uma Illuminate\Http\Response pronta para download ou exibição inline no navegador.
     */
    public function createPdfResponse(string $html, string $filename, bool $isAttachment = false, array $options = []): Response
    {
        $pdfContent = $this->renderHtmlToPdf($html, $options);
        $disposition = $isAttachment ? 'attachment' : 'inline';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Compilação via Gotenberg API REST (Chromium Engine).
     */
    protected function renderWithGotenberg(string $html, array $options = []): ?string
    {
        $timeout = (int) env('GOTENBERG_TIMEOUT', 5);
        $connectTimeout = (int) env('GOTENBERG_CONNECT_TIMEOUT', 2);

        $response = Http::connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->attach('files', $html, 'index.html')
            ->post($endpoint, [
                'paperWidth' => $options['paperWidth'] ?? '8.27',   // 210mm
                'paperHeight' => $options['paperHeight'] ?? '11.7', // 297mm
                'marginTop' => $options['marginTop'] ?? '0.78',     // 20mm
                'marginBottom' => $options['marginBottom'] ?? '1.37', // 35mm
                'marginLeft' => $options['marginLeft'] ?? '1.18',   // 30mm
                'marginRight' => $options['marginRight'] ?? '0.78',  // 20mm
                'printBackground' => 'true',
                'preferCSSPageSize' => 'true',
            ]);

        if ($response->successful()) {
            return $response->body();
        }

        Log::warning('Gotenberg retornou HTTP error status: '.$response->status());

        return null;
    }

    /**
     * Fallback de compilação em PHP puro usando Dompdf.
     */
    protected function renderWithDompdf(string $html, array $options = []): string
    {
        $dompdfOptions = new Options;
        $dompdfOptions->set('isRemoteEnabled', true);
        $dompdfOptions->set('defaultFont', $options['defaultFont'] ?? 'Times-Roman');

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($options['paperSize'] ?? 'A4', $options['orientation'] ?? 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
