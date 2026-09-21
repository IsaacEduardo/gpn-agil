<?php

namespace Tests\Feature;

use App\Services\Ocr\OcrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Escolha entre texto nativo e OCR nos PDF que chegam do scanner.
 *
 * O documento digitalizado é imagem pura: sem OCR não fica pesquisável. Mas o
 * inverso também tem de valer — um PDF que já traz texto nativo não pode ver
 * esse texto substituído por um reconhecimento aproximado.
 *
 * A suite é ignorada onde não houver Tesseract e rasterizador, porque aí o
 * resultado diria mais sobre a máquina do que sobre o código.
 */
class OcrDocumentoDigitalizadoTest extends TestCase
{
    private OcrService $ocr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ocr = app(OcrService::class);

        $diagnostico = $this->ocr->diagnose();

        if (empty($diagnostico['tesseract_binary'])) {
            $this->markTestSkipped('Tesseract não está instalado nesta máquina.');
        }

        if (! collect($diagnostico['converters'] ?? [])->contains(true)) {
            $this->markTestSkipped('Falta um rasterizador de PDF (pdftoppm, Ghostscript ou ImageMagick).');
        }
    }

    /** O PDF que sai do agente não tem camada de texto: só o OCR o torna pesquisável. */
    public function test_pdf_digitalizado_fica_pesquisavel_por_ocr(): void
    {
        $caminho = base_path('tests/fixtures/digitalizacao-com-texto.pdf');
        $this->assertFileExists($caminho);

        // Garantia da premissa: a fixture não traz texto nativo nenhum.
        $this->assertSame(0, $this->ocr->countWords($this->ocr->extractNativePdfText($caminho)));

        $resultado = $this->ocr->processFile($caminho, 'application/pdf');

        $this->assertSame('TESSERACT_OCR', $resultado['method']);
        $this->assertGreaterThan(10, $resultado['words_count']);
        $this->assertStringContainsStringIgnoringCase('NAMIBE', $resultado['text']);
        $this->assertStringContainsStringIgnoringCase('licenciamento', $resultado['text']);
    }

    /**
     * Um despacho curto com texto nativo perfeito não pode ser degradado.
     *
     * Antes, o serviço adotava o OCR sempre que ele devolvesse alguma palavra,
     * mesmo quando o texto nativo era melhor. Com poucas palavras — abaixo do
     * limiar de `min_native_words` — o original exacto era trocado por uma
     * leitura de imagem, e era essa que ficava no arquivo.
     */
    public function test_texto_nativo_nao_e_substituido_por_ocr_pior(): void
    {
        $pdf = Pdf::loadHTML(
            '<h1>Despacho</h1><p>DeferidoConformeSolicitado2026 pelo Gabinete Provincial.</p>'
        )->output();

        $caminho = tempnam(sys_get_temp_dir(), 'despacho-').'.pdf';
        file_put_contents($caminho, $pdf);

        try {
            $nativas = $this->ocr->countWords($this->ocr->extractNativePdfText($caminho));

            // A premissa do teste: texto nativo existe mas fica abaixo do limiar,
            // logo o serviço vai correr o OCR e tem de comparar os dois.
            $this->assertGreaterThan(0, $nativas);
            $this->assertLessThan((int) config('services.ocr.min_native_words', 50), $nativas);

            $resultado = $this->ocr->processFile($caminho, 'application/pdf');

            $this->assertSame('PDF_NATIVO', $resultado['method']);
            $this->assertStringContainsString('DeferidoConformeSolicitado2026', $resultado['text']);
        } finally {
            @unlink($caminho);
        }
    }
}
