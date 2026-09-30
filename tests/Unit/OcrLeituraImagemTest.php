<?php

namespace Tests\Unit;

use App\Services\Ocr\OcrService;
use Tests\TestCase;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

/**
 * OCR de imagens anexadas. Em produção, uma foto do WhatsApp e um recorte PNG de
 * 311x258 ficavam em FALHA: o Tesseract corria, não achava texto, e a biblioteca
 * tratava o resultado vazio como erro. O recorte tinha texto — só não com aquele
 * tamanho. O Tesseract e o ImageMagick são simulados: o que se testa é a decisão.
 */
class OcrLeituraImagemTest extends TestCase
{
    private string $imagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imagem = tempnam(sys_get_temp_dir(), 'ocrimg_');
        file_put_contents($this->imagem, 'png');
    }

    protected function tearDown(): void
    {
        @unlink($this->imagem);
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $leituras  "original@3", "preparada@6"… => texto (ou excepção)
     */
    private function servico(array $leituras, bool $comMagick = true): OcrService
    {
        return new class($leituras, $comMagick) extends OcrService
        {
            public array $chamadas = [];

            public ?string $preparada = null;

            public function __construct(private array $leituras, private bool $comMagick) {}

            public function runTesseract(string $imagePath, int $psm = 3): string
            {
                $origem = $imagePath === $this->preparada ? 'preparada' : 'original';
                $this->chamadas[] = "{$origem}@{$psm}";
                $resultado = $this->leituras["{$origem}@{$psm}"] ?? '';
                if ($resultado instanceof \Throwable) {
                    throw $resultado;
                }

                return $resultado;
            }

            protected function prepararImagem(string $caminho): ?string
            {
                if (! $this->comMagick) {
                    return null;
                }
                $this->preparada = tempnam(sys_get_temp_dir(), 'ocrprep_');

                return $this->preparada;
            }
        };
    }

    private function frase(int $palavras): string
    {
        return implode(' ', array_fill(0, $palavras, 'ofício'));
    }

    public function test_imagem_boa_nao_paga_segunda_leitura(): void
    {
        $ocr = $this->servico(['original@3' => $this->frase(40)]);

        $resultado = $ocr->processFile($this->imagem, 'image/png');

        $this->assertSame('IMAGEM_OCR', $resultado['method']);
        $this->assertSame(40, $resultado['words_count']);
        $this->assertSame(['original@3'], $ocr->chamadas);
    }

    public function test_imagem_fraca_e_preparada_e_fica_a_melhor_leitura(): void
    {
        $ocr = $this->servico([
            'original@3' => '',
            'preparada@3' => $this->frase(12),
            'preparada@6' => $this->frase(24),
        ]);

        $resultado = $ocr->processFile($this->imagem, 'image/png');

        $this->assertSame('IMAGEM_OCR_PREPARADA', $resultado['method']);
        $this->assertSame(24, $resultado['words_count']);
        $this->assertFileDoesNotExist($ocr->preparada, 'a cópia temporária é apagada');
    }

    public function test_leitura_original_mantida_se_a_preparada_nao_for_melhor(): void
    {
        $ocr = $this->servico([
            'original@3' => $this->frase(5),
            'preparada@3' => $this->frase(2),
            'preparada@6' => '',
        ]);

        $resultado = $ocr->processFile($this->imagem, 'image/jpeg');

        $this->assertSame('IMAGEM_OCR', $resultado['method']);
        $this->assertSame(5, $resultado['words_count']);
    }

    public function test_imagem_sem_texto_conclui_com_zero_palavras(): void
    {
        $ocr = $this->servico(['original@3' => ''], comMagick: false);

        $resultado = $ocr->processFile($this->imagem, 'image/jpeg');

        $this->assertSame(0, $resultado['words_count']);
        $this->assertSame('IMAGEM_OCR', $resultado['method']);
    }

    public function test_falha_na_releitura_nao_perde_a_leitura_original(): void
    {
        $ocr = $this->servico([
            'original@3' => $this->frase(3),
            'preparada@3' => new \RuntimeException('tesseract rebentou'),
            'preparada@6' => $this->frase(8),
        ]);

        $resultado = $ocr->processFile($this->imagem, 'image/png');

        $this->assertSame(8, $resultado['words_count']);
    }

    public function test_erro_verdadeiro_na_primeira_leitura_continua_a_falhar(): void
    {
        $ocr = $this->servico(['original@3' => new UnsuccessfulCommandException('Error! Failed loading language por')]);

        $this->expectException(UnsuccessfulCommandException::class);
        $ocr->processFile($this->imagem, 'image/png');
    }

    // --- Distinguir "sem texto" de "falhou" -------------------------------------------

    private function mensagem(string $stderr): string
    {
        return "Error! The command did not produce any output.\n\nGenerated command:\n\"/usr/bin/tesseract\" x /tmp/o\n\nReturned message:\n{$stderr}";
    }

    public function test_pagina_vazia_nao_e_erro(): void
    {
        $ocr = new OcrService;

        // Anexo 54 em produção.
        $this->assertTrue($ocr->saidaVaziaSemErro($this->mensagem("Estimating resolution as 884\nEmpty page!!\nEstimating resolution as 884\nEmpty page!!")));
        // Anexo 55: nenhum aviso.
        $this->assertTrue($ocr->saidaVaziaSemErro($this->mensagem('')));
        $this->assertTrue($ocr->saidaVaziaSemErro($this->mensagem("Warning: Invalid resolution 0 dpi. Using 70 instead.\nToo few characters. Skipping this page")));
    }

    public function test_falhas_reais_continuam_a_ser_erro(): void
    {
        $ocr = new OcrService;

        $this->assertFalse($ocr->saidaVaziaSemErro($this->mensagem("Error opening data file /usr/share/tessdata/por.traineddata\nFailed loading language 'por'")));
        $this->assertFalse($ocr->saidaVaziaSemErro($this->mensagem('Error in pixReadStream: Unknown format: no pix returned')));
        $this->assertFalse($ocr->saidaVaziaSemErro('Error! The image "x" was not found.'));
    }

    // --- ImageMagick --------------------------------------------------------------------

    public function test_convert_do_windows_nao_e_confundido_com_o_imagemagick(): void
    {
        config(['services.ocr.magick_path' => null]);
        $ocr = new class extends OcrService
        {
            protected function findInPath(string $binary): ?string
            {
                return $binary === 'convert' ? 'C:\\Windows\\System32\\convert.exe' : null;
            }
        };

        $this->assertNull($ocr->resolveMagickBinary());
    }

    public function test_convert_do_imagemagick_em_linux_continua_a_servir(): void
    {
        config(['services.ocr.magick_path' => null]);
        $ocr = new class extends OcrService
        {
            protected function findInPath(string $binary): ?string
            {
                return $binary === 'convert' ? '/usr/bin/convert' : null;
            }
        };

        $this->assertSame('/usr/bin/convert', $ocr->resolveMagickBinary());
    }
}
