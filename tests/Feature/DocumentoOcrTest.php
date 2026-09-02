<?php

namespace Tests\Feature;

use App\Jobs\ProcessarOcrAnexo;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentoOcrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private DocumentoEntrada $documento;
    private Anexo $anexo;

    protected function setUp(): void
    {
        parent::setUp();

        $dep = Departamento::factory()->create();
        $this->admin = User::factory()->create([
            'departamento_id' => $dep->id,
        ]);
        $this->admin->assignRole('admin');

        $this->documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $dep->id,
            'user_id' => $this->admin->id,
            'assunto' => 'Documento de Teste OCR',
        ]);

        $this->anexo = Anexo::create([
            'anexavel_type' => DocumentoEntrada::class,
            'anexavel_id' => $this->documento->id,
            'nome_original' => 'despacho_oficial.pdf',
            'caminho_arquivo' => 'anexos/despacho_oficial.pdf',
            'mime_type' => 'application/pdf',
            'texto_extraido' => 'Conteúdo confidencial indexado por OCR sobre orçamento municipal 2026.',
            'ocr_status' => 'CONCLUIDO',
            'ocr_metodo' => 'PDF_NATIVO',
            'ocr_palavras_count' => 10,
            'ocr_processado_em' => now(),
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_get_ocr_text_retorna_metadados_completos(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('documentos-entradas.anexos.ocr', [$this->documento, $this->anexo])
        );

        $response->assertOk()
            ->assertJson([
                'nome_original' => 'despacho_oficial.pdf',
                'texto_extraido' => 'Conteúdo confidencial indexado por OCR sobre orçamento municipal 2026.',
                'ocr_status' => 'CONCLUIDO',
                'ocr_metodo' => 'PDF_NATIVO',
                'ocr_palavras_count' => 10,
            ])
            ->assertJsonStructure([
                'nome_original',
                'texto_extraido',
                'ocr_status',
                'ocr_status_badge',
                'ocr_metodo',
                'ocr_palavras_count',
                'ocr_processado_em',
            ]);
    }

    public function test_reprocess_ocr_enfileira_job_com_sucesso(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)->postJson(
            route('documentos-entradas.anexos.reprocessar-ocr', [$this->documento, $this->anexo])
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'anexo_id' => $this->anexo->id,
                'ocr_status' => 'PENDENTE',
            ]);

        Queue::assertPushed(ProcessarOcrAnexo::class, function ($job) {
            return $job->anexoId === $this->anexo->id;
        });

        $this->assertEquals('PENDENTE', $this->anexo->fresh()->ocr_status);
    }

    public function test_pesquisa_localiza_documento_pelo_texto_do_anexo_ocr(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('documentos-entradas.search.json', ['q' => 'orçamento municipal 2026'])
        );

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertEquals($this->documento->id, $data[0]['id']);
    }
}
