<?php

namespace Tests\Unit;

use App\Jobs\ProcessarOcrAnexo;
use App\Models\Anexo;
use App\Models\DocumentoEntrada;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessarOcrAnexoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.docs_disk' => 'public']);
        Storage::fake('public');
    }

    private function makeAnexo(array $attrs = []): Anexo
    {
        $doc = DocumentoEntrada::factory()->create();

        return Anexo::create(array_merge([
            'anexavel_type' => DocumentoEntrada::class,
            'anexavel_id' => $doc->id,
            'nome_original' => 'teste.pdf',
            'caminho_arquivo' => 'anexos/teste.pdf',
            'mime_type' => 'application/pdf',
            'ocr_status' => 'PENDENTE',
        ], $attrs));
    }

    public function test_job_ignora_anexo_inexistente_sem_erro(): void
    {
        (new ProcessarOcrAnexo(999999))->handle();

        $this->assertTrue(true); // não lançou exceção
    }

    public function test_job_marca_falha_quando_arquivo_em_falta(): void
    {
        $anexo = $this->makeAnexo(['caminho_arquivo' => 'anexos/nao-existe.pdf']);

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $fresh = $anexo->fresh();
        $this->assertEquals('FALHA', $fresh->ocr_status);
        $this->assertNotNull($fresh->ocr_erro);
        $this->assertNull($fresh->texto_extraido);
    }

    public function test_job_marca_nao_aplicavel_para_formato_nao_suportado(): void
    {
        $anexo = $this->makeAnexo([
            'nome_original' => 'audio.mp3',
            'caminho_arquivo' => 'anexos/audio.mp3',
            'mime_type' => 'audio/mpeg',
        ]);
        Storage::disk('public')->put('anexos/audio.mp3', 'dummy-audio-content');

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $fresh = $anexo->fresh();
        $this->assertEquals('NAO_APLICAVEL', $fresh->ocr_status);
        $this->assertNull($fresh->texto_extraido);
    }

    public function test_job_extrai_texto_de_pdf_pesquisavel_e_atualiza_status_concluido(): void
    {
        // Gera um PDF real com texto embutido via dompdf
        $pdfContent = Pdf::loadHTML('<h1>Documento de Teste</h1><p>ConteudoUnicoParaOcr12345 emitido pelo Gabinete de Gestão Pública para validação do sistema.</p>')->output();
        Storage::disk('public')->put('anexos/teste.pdf', $pdfContent);

        $anexo = $this->makeAnexo();

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $fresh = $anexo->fresh();
        $this->assertEquals('CONCLUIDO', $fresh->ocr_status);
        $this->assertEquals('PDF_NATIVO', $fresh->ocr_metodo);
        $this->assertNotNull($fresh->ocr_processado_em);
        $this->assertGreaterThan(0, $fresh->ocr_palavras_count);
        $this->assertStringContainsString('ConteudoUnicoParaOcr12345', (string) $fresh->texto_extraido);
    }

    public function test_job_lida_com_erro_de_imagem_invalida_sem_lancar_excecao(): void
    {
        Storage::disk('public')->put('anexos/imagem.png', 'nao-e-uma-imagem-real');

        $anexo = $this->makeAnexo([
            'nome_original' => 'imagem.png',
            'caminho_arquivo' => 'anexos/imagem.png',
            'mime_type' => 'image/png',
        ]);

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $fresh = $anexo->fresh();
        $this->assertEquals('FALHA', $fresh->ocr_status);
        $this->assertNotNull($fresh->ocr_erro);
    }
}
