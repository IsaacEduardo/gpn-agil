<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O botão Imprimir da ficha do documento interno manda à impressora o PDF
 * oficial (2026-10-03): a página web impressa levava o menu do sistema e a
 * data/endereço que o browser junta às páginas HTML.
 */
class DocumentoInternoImpressaoTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $gab = Gabinete::create(['nome' => 'Gabinete Impressão']);
        $dep = Departamento::create(['nome' => 'Departamento Impressão', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $dep->id]);
        $this->doc = DocumentoInterno::factory()->create([
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
        ]);

        // O conteúdo do PDF não interessa aqui; evita-se o Gotenberg/DomPDF.
        $this->mock(PdfRenderService::class, fn ($m) => $m->shouldReceive('createPdfResponse')
            ->andReturnUsing(fn ($html, $nome, $anexo) => response('%PDF-1.4', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($anexo ? 'attachment' : 'inline')."; filename=\"{$nome}\"",
            ])));
    }

    public function test_botao_imprimir_usa_o_pdf_e_nao_a_pagina(): void
    {
        $url = route('documentos-internos.pdf', [$this->doc->id, 'imprimir' => 1]);

        $this->actingAs($this->autor)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()
            ->assertSee('id="btnImprimirDocumento"', false)
            ->assertSee('data-pdf-url="'.e($url).'"', false)
            ->assertDontSee('onclick="window.print()"', false);
    }

    public function test_pdf_para_imprimir_abre_no_browser_e_fica_registado_como_impressao(): void
    {
        $this->actingAs($this->autor)->get(route('documentos-internos.pdf', [$this->doc->id, 'imprimir' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="Documento_'.str_replace('/', '-', $this->doc->numero_referencia).'.pdf"');

        $this->assertTrue(AuditLog::where('auditable_id', $this->doc->id)->where('action', 'print')->exists());
        $this->assertFalse(AuditLog::where('auditable_id', $this->doc->id)->where('action', 'download')->exists());
    }

    public function test_pdf_nao_tem_numeracao_de_paginas(): void
    {
        // O Dompdf não calcula o total de páginas: o rodapé dizia "Página 1 de 0".
        $this->actingAs($this->autor);
        $html = view('documentos_internos.pdf', ['documentoInterno' => $this->doc->fresh()])->render();

        $this->assertStringNotContainsString('Página <span', $html);
        $this->assertStringNotContainsString('counter(pages)', $html);
    }

    public function test_baixar_continua_registado_como_descarga(): void
    {
        $this->actingAs($this->autor)->get(route('documentos-internos.pdf', $this->doc->id))->assertOk();

        $this->assertTrue(AuditLog::where('auditable_id', $this->doc->id)->where('action', 'download')->exists());
    }
}
