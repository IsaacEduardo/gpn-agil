<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoInternoListagemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $departamento;
    protected $gabinete;
    protected $especie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::create(['nome' => 'Gabinete Provincial', 'sigla' => 'GP']);
        $this->departamento = Departamento::create([
            'nome' => 'Departamento de Tecnologia',
            'sigla' => 'DTI',
            'gabinete_id' => $this->gabinete->id,
        ]);
        $this->user = User::factory()->create([
            'name' => 'Técnico Isaac',
            'departamento_id' => $this->departamento->id,
        ]);
        $this->especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);

        $this->actingAs($this->user);
    }

    public function test_index_page_loads_correctly_for_tecnico(): void
    {
        $response = $this->get(route('documentos-internos.index'));
        $response->assertStatus(200);
        $response->assertSee('Documentos Internos');
        $response->assertSee('Meus Rascunhos');
        $response->assertSee('Em Revisão');
        $response->assertSee('Aprovados do Departamento');
    }

    public function test_tecnico_so_visualiza_seus_proprios_rascunhos_e_documentos_oficiais_do_setor(): void
    {
        $outroTecnico = User::factory()->create([
            'name' => 'Outro Técnico',
            'departamento_id' => $this->departamento->id,
        ]);

        // Rascunho do usuário logado
        $meuRascunho = DocumentoInterno::create([
            'titulo' => 'Meu Rascunho Confidencial',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/MEU-01',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        // Rascunho de outro técnico (NÃO DEVE SER VISÍVEL PARA O TÉCNICO LOGADO)
        $rascunhoAlheio = DocumentoInterno::create([
            'titulo' => 'Rascunho Secreto Alheio',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $outroTecnico->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/ALHEIO-01',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        // Documento aprovado de outro técnico (DEVE SER VISÍVEL PARA CONSULTA COLETIVA)
        $docOficialAlheio = DocumentoInterno::create([
            'titulo' => 'Normativa Oficial Setorial',
            'conteudo_final' => 'Conteúdo aprovado',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $outroTecnico->id,
            'status' => 'aprovado',
            'numero_referencia' => 'REF/OFICIAL-01',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        // Na aba padrão (Meus Rascunhos)
        $response = $this->get(route('documentos-internos.index', ['tab' => 'meus_rascunhos']));
        $response->assertOk();
        $response->assertSee('Meu Rascunho Confidencial');
        $response->assertDontSee('Rascunho Secreto Alheio');
        $response->assertDontSee('Normativa Oficial Setorial');

        // Na aba Aprovados do Departamento
        $responseAprovados = $this->get(route('documentos-internos.index', ['tab' => 'aprovados']));
        $responseAprovados->assertOk();
        $responseAprovados->assertSee('Normativa Oficial Setorial');
        $responseAprovados->assertDontSee('Rascunho Secreto Alheio');
    }

    public function test_chefe_departamento_visualiza_todos_os_documentos_e_rascunhos_do_setor(): void
    {
        $chefe = User::factory()->create([
            'name' => 'Chefe de Departamento',
            'departamento_id' => $this->departamento->id,
        ]);
        $this->departamento->update(['responsavel_id' => $chefe->id]);

        $docEmRevisao = DocumentoInterno::create([
            'titulo' => 'Proposta de Parecer do Técnico',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'em_analise',
            'numero_referencia' => 'REF/ANALISE-01',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        $response = $this->actingAs($chefe)->get(route('documentos-internos.index'));
        $response->assertOk();
        $response->assertSee('Aguardando Revisão');
        $response->assertSee('Em Elaboração');
        $response->assertSee('Assinados / Expedidos');
        $response->assertSee('Todos do Departamento');
        $response->assertSee('Proposta de Parecer do Técnico');
    }

    public function test_chefe_gabinete_visualiza_documentos_de_multiplos_departamentos(): void
    {
        $dep2 = Departamento::create([
            'nome' => 'Departamento de Finanças',
            'sigla' => 'DFIN',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $chefeGabinete = User::factory()->create([
            'name' => 'Dr. Chefe de Gabinete',
            'departamento_id' => $this->departamento->id,
        ]);
        $this->gabinete->update(['responsavel_id' => $chefeGabinete->id]);

        // Doc em análise no DTI
        DocumentoInterno::create([
            'titulo' => 'Despacho DTI para Gabinete',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'em_analise',
            'numero_referencia' => 'DTI/001',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        // Doc em análise no DFIN
        DocumentoInterno::create([
            'titulo' => 'Orçamento DFIN para Homologação',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $dep2->id,
            'criado_por' => $this->user->id,
            'status' => 'em_analise',
            'numero_referencia' => 'DFIN/001',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        $response = $this->actingAs($chefeGabinete)->get(route('documentos-internos.index'));
        $response->assertOk();
        $response->assertSee('Para Homologação');
        $response->assertSee('Assinados / Homologados');
        $response->assertSee('Todos do Gabinete');
        $response->assertSee('Despacho DTI para Gabinete');
        $response->assertSee('Orçamento DFIN para Homologação');
        $response->assertSee('Departamento de Tecnologia');
        $response->assertSee('Departamento de Finanças');
    }

    public function test_busca_retratil_filtra_documentos_por_titulo_e_referencia(): void
    {
        DocumentoInterno::create([
            'titulo' => 'Documento Alfa Específico',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/ALFA-99',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        DocumentoInterno::create([
            'titulo' => 'Outro Documento Beta',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/BETA-88',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        $response = $this->get(route('documentos-internos.index', ['search' => 'ALFA-99']));
        $response->assertOk();
        $response->assertSee('Documento Alfa Específico');
        $response->assertDontSee('Outro Documento Beta');
    }

    public function test_preview_ajax_returns_partial_html(): void
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Doc Preview Teste',
            'conteudo_final' => '<p>Conteudo de Teste Preview</p>',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/PREVIEW',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
        ]);

        $response = $this->get(route('documentos-internos.show', ['documentoInterno' => $doc->id, 'preview' => 'true']));
        $response->assertStatus(200);
        $response->assertJsonStructure(['html']);
        $content = $response->json('html');
        $this->assertStringContainsString('Conteudo de Teste Preview', $content);
        $this->assertStringContainsString('REPÚBLICA DE ANGOLA', $content);
    }
}
