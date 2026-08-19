<?php

namespace Tests\Unit\Services;

use App\Services\PdfRenderService;
use Tests\TestCase;

class PdfRenderServiceTest extends TestCase
{
    public function test_pdf_render_service_generates_pdf_content_via_fallback()
    {
        $service = new PdfRenderService();
        $html = '<html><body><h1>Documento de Teste</h1><p>Conteúdo de teste para geração de PDF.</p></body></html>';

        $pdfBin = $service->renderHtmlToPdf($html);

        $this->assertNotEmpty($pdfBin);
        $this->assertStringStartsWith('%PDF-', $pdfBin);
    }

    public function test_pdf_render_service_returns_valid_http_response()
    {
        $service = new PdfRenderService();
        $html = '<html><body><p>Teste de Download Response</p></body></html>';

        $response = $service->createPdfResponse($html, 'teste_doc.pdf', false);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="teste_doc.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_render_service_falls_back_on_gotenberg_timeout()
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://127.0.0.1:3000/*' => \Illuminate\Support\Facades\Http::response('Timeout', 504),
        ]);

        $service = new PdfRenderService();
        $html = '<html><body><h1>Teste de Fallback em Timeout</h1></body></html>';

        $pdfBin = $service->renderHtmlToPdf($html);

        $this->assertNotEmpty($pdfBin);
        $this->assertStringStartsWith('%PDF-', $pdfBin);
    }
}
