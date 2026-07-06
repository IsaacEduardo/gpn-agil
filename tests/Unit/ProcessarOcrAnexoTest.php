<?php

namespace Tests\Unit;

use App\Jobs\ProcessarOcrAnexo;
use App\Models\Anexo;
use App\Models\DocumentoEntrada;
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
        ], $attrs));
    }

    public function test_job_ignora_anexo_inexistente_sem_erro(): void
    {
        (new ProcessarOcrAnexo(999999))->handle();

        $this->assertTrue(true); // não lançou exceção
    }

    public function test_job_ignora_ficheiro_em_falta_sem_erro(): void
    {
        $anexo = $this->makeAnexo(['caminho_arquivo' => 'anexos/nao-existe.pdf']);

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $this->assertNull($anexo->fresh()->texto_extraido);
    }

    public function test_job_extrai_texto_de_pdf_pesquisavel(): void
    {
        // Gera um PDF real com texto embutido via dompdf
        $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>ConteudoUnicoParaOcr12345</p>')->output();
        Storage::disk('public')->put('anexos/teste.pdf', $pdfContent);

        $anexo = $this->makeAnexo();

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $this->assertStringContainsString('ConteudoUnicoParaOcr12345', (string) $anexo->fresh()->texto_extraido);
    }

    public function test_job_nao_falha_quando_ocr_de_imagem_indisponivel(): void
    {
        // Imagem inválida/ambiente sem Tesseract: o job deve capturar a falha e seguir
        Storage::disk('public')->put('anexos/imagem.png', 'nao-e-uma-imagem-real');

        $anexo = $this->makeAnexo([
            'nome_original' => 'imagem.png',
            'caminho_arquivo' => 'anexos/imagem.png',
            'mime_type' => 'image/png',
        ]);

        (new ProcessarOcrAnexo($anexo->id))->handle();

        $this->assertNull($anexo->fresh()->texto_extraido);
    }
}
