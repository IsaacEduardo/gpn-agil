<?php

namespace Tests\Feature;

use App\Services\CompressorPdf;
use App\Services\Ocr\OcrService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Rede de segurança contra PDF pesados no upload. O que importa é o que nunca
 * pode acontecer: estragar um PDF assinado, trocar o ficheiro por um pior ou
 * com páginas a menos, ou impedir o registo por falta do Ghostscript.
 */
class CompressorPdfTest extends TestCase
{
    /** @var list<string> */
    private array $temporarios = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['documentos.compressao_pdf' => ['ativa' => true, 'limite_kb' => 1024, 'resolucao_dpi' => 200, 'timeout_segundos' => 30]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $caminho) {
            @unlink($caminho);
        }
        parent::tearDown();
    }

    /** PDF com o tamanho pedido; o enchimento finge as imagens pesadas. */
    private function pdf(int $kb, string $extra = ''): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'cmp-');
        $this->temporarios[] = $caminho;
        file_put_contents($caminho, "%PDF-1.4\n{$extra}\n".str_repeat('x', $kb * 1024)."\n%%EOF\n");

        return new UploadedFile($caminho, 'digitalizacao.pdf', 'application/pdf', null, true);
    }

    /**
     * Compressor com o Ghostscript e o pdfinfo simulados: o "Ghostscript"
     * escreve um PDF de $kbSaida KB com $paginasSaida páginas.
     */
    private function compressor(?string $gs = 'gs-falso', int $kbSaida = 100, int $paginasSaida = 3, bool $gsFalha = false): CompressorPdf
    {
        $ocr = new class($gs) extends OcrService
        {
            public function __construct(private ?string $gs) {}

            public function resolveGhostscriptBinary(): ?string
            {
                return $this->gs;
            }
        };

        return new class($ocr, $kbSaida, $paginasSaida, $gsFalha) extends CompressorPdf
        {
            public function __construct(OcrService $ocr, private int $kbSaida, private int $paginasSaida, private bool $gsFalha)
            {
                parent::__construct($ocr);
            }

            protected function executarGhostscript(string $gs, string $entrada, string $saida): bool
            {
                if ($this->gsFalha) {
                    return false;
                }
                file_put_contents($saida, "%PDF-1.5\n".str_repeat('y', $this->kbSaida * 1024)."\n%%EOF\n");

                return true;
            }

            protected function contarPaginas(string $caminho): ?int
            {
                return str_contains((string) file_get_contents($caminho, false, null, 0, 8), '%PDF-1.5') ? $this->paginasSaida : 3;
            }
        };
    }

    public function test_pdf_pesado_e_substituido_pelo_comprimido_e_o_tamanho_acompanha(): void
    {
        $ficheiro = $this->pdf(2048);

        $this->assertTrue($this->compressor(kbSaida: 300)->comprimirUpload($ficheiro));

        $this->assertStringStartsWith('%PDF-1.5', file_get_contents($ficheiro->getRealPath()));
        // O registo grava $file->getSize(): tem de ver o tamanho novo.
        $this->assertLessThan(400 * 1024, $ficheiro->getSize());
    }

    public function test_pdf_abaixo_do_limite_nao_e_tocado(): void
    {
        $ficheiro = $this->pdf(500);
        $antes = file_get_contents($ficheiro->getRealPath());

        $this->assertFalse($this->compressor()->comprimirUpload($ficheiro));
        $this->assertSame($antes, file_get_contents($ficheiro->getRealPath()));
    }

    public function test_pdf_assinado_nunca_e_reescrito(): void
    {
        $ficheiro = $this->pdf(2048, '<< /Type /Sig /Filter /Adobe.PPKLite /ByteRange [0 100 200 300] >>');

        $this->assertFalse($this->compressor()->comprimirUpload($ficheiro));
        $this->assertStringStartsWith('%PDF-1.4', file_get_contents($ficheiro->getRealPath()));
    }

    /** A leitura é por blocos de 1 MB: uma marca partida entre dois blocos tem de ser apanhada. */
    public function test_assinatura_a_meio_do_ficheiro_e_na_fronteira_dos_blocos_e_detectada(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'cmp-');
        $this->temporarios[] = $caminho;
        $marca = '/ByteRange [0 1 2 3]';
        $antes = 1024 * 1024 - 5; // a marca começa 5 bytes antes do fim do 1.º bloco
        file_put_contents($caminho, "%PDF-1.4\n".str_repeat('x', $antes - 9).$marca.str_repeat('x', 2 * 1024 * 1024)."\n%%EOF\n");

        $this->assertTrue($this->compressor()->ficheiroAssinadoOuCifrado($caminho));
        $this->assertFalse($this->compressor()->comprimirUpload(new UploadedFile($caminho, 'a.pdf', 'application/pdf', null, true)));
    }

    public function test_pdf_cifrado_nao_e_tocado(): void
    {
        $ficheiro = $this->pdf(2048, 'trailer << /Encrypt 5 0 R >>');

        $this->assertFalse($this->compressor()->comprimirUpload($ficheiro));
    }

    public function test_imagem_nao_e_tocada(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'cmp-');
        $this->temporarios[] = $caminho;
        file_put_contents($caminho, "\xFF\xD8\xFF\xE0".str_repeat('z', 2 * 1024 * 1024));

        $this->assertFalse($this->compressor()->comprimirUpload(new UploadedFile($caminho, 'foto.jpg', 'image/jpeg', null, true)));
    }

    public function test_ganho_pequeno_mantem_o_original(): void
    {
        $ficheiro = $this->pdf(2048);

        $this->assertFalse($this->compressor(kbSaida: 1950)->comprimirUpload($ficheiro));
        $this->assertStringStartsWith('%PDF-1.4', file_get_contents($ficheiro->getRealPath()));
    }

    public function test_paginas_diferentes_mantem_o_original(): void
    {
        $ficheiro = $this->pdf(2048);

        $this->assertFalse($this->compressor(kbSaida: 200, paginasSaida: 2)->comprimirUpload($ficheiro));
        $this->assertStringStartsWith('%PDF-1.4', file_get_contents($ficheiro->getRealPath()));
    }

    public function test_sem_ghostscript_ou_com_falha_o_ficheiro_segue_como_veio(): void
    {
        $semGs = $this->pdf(2048);
        $this->assertFalse($this->compressor(gs: null)->comprimirUpload($semGs));

        $comFalha = $this->pdf(2048);
        $this->assertFalse($this->compressor(gsFalha: true)->comprimirUpload($comFalha));
        $this->assertStringStartsWith('%PDF-1.4', file_get_contents($comFalha->getRealPath()));
        $this->assertFileDoesNotExist($comFalha->getRealPath().'.gs.pdf');
    }

    public function test_desligado_na_configuracao_nao_faz_nada(): void
    {
        config(['documentos.compressao_pdf.ativa' => false]);

        $this->assertFalse($this->compressor()->comprimirUpload($this->pdf(2048)));
    }

    /** Ghostscript verdadeiro, quando existe (produção): um PDF real fica válido e mais leve. */
    public function test_com_ghostscript_real_o_resultado_e_um_pdf_valido(): void
    {
        $ocr = new OcrService;
        if (! $ocr->resolveGhostscriptBinary() || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Ghostscript ou GD indisponível neste ambiente.');
        }

        // Página A4 a 300 dpi com ruído, em JPEG de qualidade máxima, como o agente antigo a gravava.
        $imagem = imagecreatetruecolor(2480, 3508);
        for ($i = 0; $i < 60000; $i++) {
            imagesetpixel($imagem, random_int(0, 2479), random_int(0, 3507), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagejpeg($imagem, null, 100);
        $jpeg = ob_get_clean();

        $w = 595;
        $h = 842;
        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Resources << /XObject << /Im0 5 0 R >> >> /Contents 4 0 R >>",
            "<< /Length 30 >>\nstream\nq {$w} 0 0 {$h} 0 0 cm /Im0 Do Q\nendstream",
            '<< /Type /XObject /Subtype /Image /Width 2480 /Height 3508 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n{$jpeg}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n".implode('', array_map(fn ($o) => sprintf("%010d 00000 n \n", $o), $offsets));
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $caminho = tempnam(sys_get_temp_dir(), 'cmp-');
        $this->temporarios[] = $caminho;
        file_put_contents($caminho, $pdf);
        $antes = filesize($caminho);
        $ficheiro = new UploadedFile($caminho, 'antigo.pdf', 'application/pdf', null, true);

        $this->assertTrue(app(CompressorPdf::class)->comprimirUpload($ficheiro), 'O Ghostscript devia ter comprimido o PDF.');
        $this->assertLessThan($antes, $ficheiro->getSize());
        $this->assertStringStartsWith('%PDF-', file_get_contents($caminho));
    }
}
