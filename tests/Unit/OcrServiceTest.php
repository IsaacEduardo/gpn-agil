<?php

namespace Tests\Unit;

use App\Services\Ocr\OcrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    private OcrService $ocrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ocrService = new OcrService;
        Storage::fake('public');
    }

    public function test_diagnose_returns_valid_structure(): void
    {
        $diag = $this->ocrService->diagnose();

        $this->assertArrayHasKey('status', $diag);
        $this->assertArrayHasKey('tesseract_binary', $diag);
        $this->assertArrayHasKey('languages', $diag);
        $this->assertArrayHasKey('has_portuguese', $diag);
        $this->assertArrayHasKey('has_english', $diag);
        $this->assertArrayHasKey('converters', $diag);
        $this->assertIsArray($diag['converters']);
    }

    public function test_sanitize_text_cleans_control_characters_and_preserves_portuguese(): void
    {
        $raw = "Texto com \x00 byte nulo, \x07 bell e caracteres acentuados: Direção-Geral de Administração Pública.\r\n\r\n\r\n\r\nNova linha.";
        $clean = $this->ocrService->sanitizeText($raw);

        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringNotContainsString("\x07", $clean);
        $this->assertStringNotContainsString("\r", $clean);
        $this->assertStringContainsString('Direção-Geral de Administração Pública.', $clean);
        $this->assertStringNotContainsString("\n\n\n", $clean);
    }

    public function test_count_words_accurately_counts_accented_words(): void
    {
        $text = 'O Gabinete do Presidente aprovou a resolução e o despacho ministerial com êxito.';
        $count = $this->ocrService->countWords($text);

        // 13 palavras
        $this->assertEquals(13, $count);
    }

    public function test_extract_native_pdf_text_on_searchable_pdf(): void
    {
        $html = '<h1>Relatório Oficial de Gestão Pública e Governança</h1><p>Este documento oficial contém texto pesquisável nativo com mais de cinquenta palavras para validação do pipeline inteligente de processamento de documentos externos e internos. A extração direta de texto economiza recursos computacionais ao evitar a conversão para imagem e o OCR redundante. A inteligência do sistema categoriza adequadamente este arquivo e indexa o seu conteúdo para a pesquisa global e assistente inteligente da instituição.</p>';
        $pdfContent = Pdf::loadHTML($html)->output();

        $tempPath = tempnam(sys_get_temp_dir(), 'ocr_test_') . '.pdf';
        file_put_contents($tempPath, $pdfContent);

        try {
            $result = $this->ocrService->processFile($tempPath, 'application/pdf');

            $this->assertEquals('PDF_NATIVO', $result['method']);
            $this->assertGreaterThanOrEqual(25, $result['words_count']);
            $this->assertStringContainsString('Relatório Oficial de Gestão', $result['text']);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function test_process_file_with_image_runs_tesseract_ocr(): void
    {
        // Cria uma imagem simples com texto usando GD
        if (! function_exists('imagecreate')) {
            $this->markTestSkipped('Extensão GD não disponível.');
        }

        $image = imagecreatetruecolor(400, 100);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, 400, 100, $bg);
        imagestring($image, 5, 20, 40, 'TESTE GPN OCR 12345', $textColor);

        $tempImagePath = tempnam(sys_get_temp_dir(), 'ocr_img_') . '.png';
        imagepng($image, $tempImagePath);
        imagedestroy($image);

        try {
            $binary = $this->ocrService->resolveTesseractBinary();
            if (! $binary || ! file_exists($binary)) {
                $this->markTestSkipped('Tesseract binary não disponível.');
            }

            $result = $this->ocrService->processFile($tempImagePath, 'image/png');

            $this->assertEquals('IMAGEM_OCR', $result['method']);
            $this->assertStringContainsString('TESTE', strtoupper($result['text']));
        } finally {
            if (file_exists($tempImagePath)) {
                @unlink($tempImagePath);
            }
        }
    }
}
