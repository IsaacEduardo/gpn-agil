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

    protected function setUp(): void
    {
        parent::setUp();

        // Setup básico
        $gabinete = Gabinete::create(['nome' => 'Gab Teste', 'sigla' => 'GT']);
        $this->departamento = Departamento::create(['nome' => 'Dept Teste', 'sigla' => 'DT', 'gabinete_id' => $gabinete->id]);
        $this->user = User::factory()->create(['departamento_id' => $this->departamento->id]);

        $this->actingAs($this->user);
    }

    public function test_index_page_loads_correctly()
    {
        $response = $this->get(route('documentos-internos.index'));
        $response->assertStatus(200);
        $response->assertSee('Documentos Internos');
    }

    public function test_filter_by_author()
    {
        // Criar outro usuário no mesmo departamento
        $otherUser = User::factory()->create(['departamento_id' => $this->departamento->id, 'name' => 'Outro Autor']);

        // Doc do usuário logado
        DocumentoInterno::create([
            'titulo' => 'Doc Meu',
            'conteudo_final' => 'Conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/001',
            'versao_atual' => 1,
            'documento_especie_id' => DocumentoEspecie::create(['nome' => 'Tipo A', 'ativo' => true])->id,
        ]);

        // Doc do outro usuário
        DocumentoInterno::create([
            'titulo' => 'Doc Outro',
            'conteudo_final' => 'Conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $otherUser->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/002',
            'versao_atual' => 1,
            'documento_especie_id' => 1,
        ]);

        // Filtrar pelo outro usuário
        $response = $this->get(route('documentos-internos.index', ['autor_id' => $otherUser->id]));

        $response->assertStatus(200);
        $response->assertSee('Doc Outro');
        $response->assertDontSee('Doc Meu');
    }

    public function test_sorting_functionality()
    {
        $especie = DocumentoEspecie::create(['nome' => 'Tipo A', 'ativo' => true]);

        DocumentoInterno::create([
            'titulo' => 'Alfa',
            'conteudo_final' => 'Conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/001',
            'versao_atual' => 1,
            'documento_especie_id' => $especie->id,
            'created_at' => now()->subDay(),
        ]);

        DocumentoInterno::create([
            'titulo' => 'Beta',
            'conteudo_final' => 'Conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/002',
            'versao_atual' => 1,
            'documento_especie_id' => $especie->id,
            'created_at' => now(),
        ]);

        // Ordenar por Título ASC
        $response = $this->get(route('documentos-internos.index', ['sort_by' => 'titulo', 'order' => 'asc']));
        $response->assertSeeInOrder(['Alfa', 'Beta']);

        // Ordenar por Título DESC
        $response = $this->get(route('documentos-internos.index', ['sort_by' => 'titulo', 'order' => 'desc']));
        $response->assertSeeInOrder(['Beta', 'Alfa']);
    }

    public function test_preview_ajax_returns_partial_html()
    {
        $especie = DocumentoEspecie::create(['nome' => 'Tipo A', 'ativo' => true]);

        $doc = DocumentoInterno::create([
            'titulo' => 'Doc Preview',
            'conteudo_final' => '<p>Conteudo de Teste Preview</p>',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => 'rascunho',
            'numero_referencia' => 'REF/PREVIEW',
            'versao_atual' => 1,
            'documento_especie_id' => $especie->id,
        ]);

        // Request com parametro preview=true para garantir que entre no bloco correto
        $response = $this->get(route('documentos-internos.show', ['documentoInterno' => $doc->id, 'preview' => 'true']));

        $response->assertStatus(200);

        // Debug se falhar
        if (! $response->headers->contains('content-type', 'application/json')) {
            dump($response->getContent());
        }

        $response->assertJsonStructure(['html']);

        // Verificar se o HTML retornado contém o conteúdo do documento
        $content = $response->json('html');
        $this->assertStringContainsString('Conteudo de Teste Preview', $content);
        $this->assertStringContainsString('REPÚBLICA DE ANGOLA', $content); // Verifica se carregou o partial paper

        // Verificar se NÃO contém o layout completo (ex: navbar)
        $this->assertStringNotContainsString('<nav class="navbar', $content);
    }
}
