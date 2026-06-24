<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdmsArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $departamento;

    protected $gabinete;

    protected $especie;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base
        $this->gabinete = Gabinete::create(['nome' => 'Gabinete de Teste', 'sigla' => 'GT']);
        $this->departamento = Departamento::create(['nome' => 'Departamento de Teste', 'sigla' => 'DT', 'gabinete_id' => $this->gabinete->id]);
        $this->user = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $this->especie = DocumentoEspecie::create(['nome' => 'Memorando', 'ativo' => true]);

        $this->actingAs($this->user);
    }

    public function test_edms_index_loads_correctly_with_pending_lists()
    {
        // Criar documentos pendentes
        $docInterno = DocumentoInterno::create([
            'titulo' => 'Memorando Pendente',
            'conteudo_final' => 'Conteudo do memorando',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => DocumentoStatus::ASSINADO,
            'numero_referencia' => 'REF/MEM/001',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
            'arquivado' => false,
        ]);

        $docEntrada = DocumentoEntrada::create([
            'assunto' => 'Oficio de Entrada Pendente',
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'data_entrada' => now(), // Required
            'procedencia' => 'Ministério da Administração',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);

        $response = $this->get(route('edms.index'));

        $response->assertStatus(200);
        $response->assertSee('EDMS - Gestão de Arquivos Digitais');
        $response->assertSee('Memorando Pendente');
        $response->assertSee('Oficio de Entrada Pendente');
    }

    public function test_search_filters_folders_and_documents_recursively()
    {
        // Criar pastas
        $pastaBusca = Pasta::create([
            'nome' => 'Pasta Alvo de Pesquisa',
            'departamento_id' => $this->departamento->id,
            'gabinete_id' => $this->gabinete->id,
            'created_by' => $this->user->id,
            'type' => 'custom',
        ]);

        $pastaOutra = Pasta::create([
            'nome' => 'Outra Pasta Qualquer',
            'departamento_id' => $this->departamento->id,
            'gabinete_id' => $this->gabinete->id,
            'created_by' => $this->user->id,
            'type' => 'custom',
        ]);

        // Criar documentos arquivados
        $docInternoBusca = DocumentoInterno::create([
            'titulo' => 'Relatorio Importante de Pesquisa',
            'conteudo_final' => 'Conteudo alvo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => DocumentoStatus::ASSINADO,
            'numero_referencia' => 'REF/REL/001',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
            'pasta_id' => $pastaBusca->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $this->user->id,
        ]);

        $docInternoOutro = DocumentoInterno::create([
            'titulo' => 'Memorando Comum',
            'conteudo_final' => 'Outro conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => DocumentoStatus::ASSINADO,
            'numero_referencia' => 'REF/MEM/002',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
            'pasta_id' => $pastaOutra->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $this->user->id,
        ]);

        // Buscar pelo termo "Pesquisa"
        $response = $this->get(route('edms.index', ['search' => 'Pesquisa']));

        $response->assertStatus(200);
        $response->assertSee('Pasta Alvo de Pesquisa');
        $response->assertSee('Relatorio Importante de Pesquisa');

        // Assert view has specific filtered items in the variables
        $response->assertViewHas('pastas', function ($pastas) use ($pastaBusca, $pastaOutra) {
            return $pastas->contains($pastaBusca) && ! $pastas->contains($pastaOutra);
        });

        $response->assertViewHas('documentosInternos', function ($docs) use ($docInternoBusca, $docInternoOutro) {
            return $docs->contains($docInternoBusca) && ! $docs->contains($docInternoOutro);
        });
    }

    public function test_filter_by_type_in_edms()
    {
        $pasta = Pasta::create([
            'nome' => 'Arquivo Geral',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
        ]);

        // Criar documentos arquivados de tipos diferentes
        $docInt = DocumentoInterno::create([
            'titulo' => 'Documento Interno Arquivado',
            'conteudo_final' => 'Conteudo',
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => DocumentoStatus::ASSINADO,
            'numero_referencia' => 'REF/INT/999',
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
            'pasta_id' => $pasta->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $this->user->id,
        ]);

        $docEnt = DocumentoEntrada::create([
            'assunto' => 'Documento de Entrada Arquivado',
            'numero_sequencial' => 10,
            'ano_referencia' => 2026,
            'data_entrada' => now(), // Required
            'procedencia' => 'Governo Provincial',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'pasta_id' => $pasta->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $this->user->id,
        ]);

        // Filtrar por Interno
        $responseInterno = $this->get(route('edms.index', ['folder' => $pasta->id, 'type' => 'interno']));
        $responseInterno->assertViewHas('documentosInternos', function ($docs) use ($docInt) {
            return $docs->contains($docInt);
        });
        $responseInterno->assertViewHas('documentosEntrada', function ($docs) {
            return $docs->isEmpty();
        });

        // Filtrar por Entrada
        $responseEntrada = $this->get(route('edms.index', ['folder' => $pasta->id, 'type' => 'entrada']));
        $responseEntrada->assertViewHas('documentosEntrada', function ($docs) use ($docEnt) {
            return $docs->contains($docEnt);
        });
        $responseEntrada->assertViewHas('documentosInternos', function ($docs) {
            return $docs->isEmpty();
        });
    }

    public function test_archiving_a_pending_document()
    {
        $pasta = Pasta::create([
            'nome' => 'Correspondência Recebida',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
        ]);

        $docEntrada = DocumentoEntrada::create([
            'assunto' => 'Oficio de Entrada a Arquivar',
            'numero_sequencial' => 5,
            'ano_referencia' => 2026,
            'data_entrada' => now(), // Required
            'procedencia' => 'Prefeitura',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);

        // Chamar rota de arquivamento
        $response = $this->post(route('pastas.arquivar', $docEntrada->id), [
            'pasta_id' => $pasta->id,
            'tipo' => 'entrada',
        ]);

        $response->assertRedirect();

        // Verificar se foi arquivado no banco
        $docEntrada->refresh();
        $this->assertTrue($docEntrada->arquivado);
        $this->assertEquals($pasta->id, $docEntrada->pasta_id);
    }

    public function test_auto_archiving_chronologically_creates_correct_folders()
    {
        $docEntrada = DocumentoEntrada::create([
            'assunto' => 'Oficio de Entrada Cronologico',
            'numero_sequencial' => 6,
            'ano_referencia' => 2026,
            'data_entrada' => \Carbon\Carbon::parse('2026-06-15'), // June
            'procedencia' => 'Prefeitura',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);

        // Chamar rota de arquivamento com 'auto'
        $response = $this->post(route('pastas.arquivar', $docEntrada->id), [
            'pasta_id' => 'auto',
            'tipo' => 'entrada',
        ]);

        $response->assertRedirect();

        // Verificar se o documento foi arquivado
        $docEntrada->refresh();
        $this->assertTrue($docEntrada->arquivado);
        $this->assertEquals('arquivado', $docEntrada->status);
        $this->assertNotNull($docEntrada->pasta_id);

        // Verificar se a pasta criada é a correta
        $pastaMensal = $docEntrada->pasta;
        $this->assertNotNull($pastaMensal);
        $this->assertEquals('06 - Junho', $pastaMensal->nome);
        $this->assertEquals(\App\Enums\PastaTipo::ENTRADA_MES->value, $pastaMensal->type);

        $pastaAnual = $pastaMensal->parent;
        $this->assertNotNull($pastaAnual);
        $this->assertEquals('2026', $pastaAnual->nome);
        $this->assertEquals(\App\Enums\PastaTipo::ENTRADA_ANO->value, $pastaAnual->type);

        $pastaRaiz = $pastaAnual->parent;
        $this->assertNotNull($pastaRaiz);
        $this->assertEquals('01. Correspondência Recebida', $pastaRaiz->nome);
        $this->assertEquals(\App\Enums\PastaTipo::ENTRADA->value, $pastaRaiz->type);
    }

    public function test_arquivar_rejects_incompatible_folder()
    {
        // Criar uma pasta exclusiva para Documentos Internos (Despachos)
        $pastaInterna = Pasta::create([
            'nome' => 'Despachos',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
            'type' => \App\Enums\PastaTipo::INTERNO_DESPACHOS->value,
        ]);

        $docEntrada = DocumentoEntrada::create([
            'assunto' => 'Oficio de Entrada a Arquivar',
            'numero_sequencial' => 7,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'procedencia' => 'Prefeitura',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);

        // Tentar arquivar documento de entrada (externo) na pasta interna
        $response = $this->post(route('pastas.arquivar', $docEntrada->id), [
            'pasta_id' => $pastaInterna->id,
            'tipo' => 'entrada',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'A pasta selecionada não é compatível com este tipo de documento.');

        // Verificar se não foi arquivado
        $docEntrada->refresh();
        $this->assertFalse($docEntrada->arquivado);
        $this->assertNull($docEntrada->pasta_id);
    }
}
