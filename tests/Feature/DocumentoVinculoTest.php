<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Enums\TipoDocumentoVinculo;
use App\Enums\TipoRelacaoDocumento;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVinculo;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoVinculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoVinculoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $chefeDept;
    protected User $tecnico1;
    protected User $tecnico2;
    protected Departamento $deptA;
    protected Departamento $deptB;
    protected Gabinete $gabinete;
    protected DocumentoEspecie $especieOficio;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['guard_name' => 'web']);
        $roleTecnico = Role::firstOrCreate(['name' => 'tecnico'], ['guard_name' => 'web']);

        $this->gabinete = Gabinete::create([
            'nome' => 'Gabinete do Governador',
            'sigla' => 'GAB.GOV',
        ]);

        $this->deptA = Departamento::create([
            'nome' => 'Departamento de Recursos Humanos',
            'sigla' => 'DRH',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->deptB = Departamento::create([
            'nome' => 'Departamento de Infraestrutura',
            'sigla' => 'DINFRA',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->admin = User::factory()->create([
            'departamento_id' => $this->deptA->id,
            'role_id' => $roleAdmin->id,
        ]);
        $this->admin->assignRole('admin');

        $this->chefeDept = User::factory()->create([
            'departamento_id' => $this->deptA->id,
            'role_id' => $roleChefe->id,
        ]);
        $this->chefeDept->assignRole('chefe-departamento');
        $this->deptA->update(['responsavel_id' => $this->chefeDept->id]);

        $this->tecnico1 = User::factory()->create([
            'departamento_id' => $this->deptA->id,
            'role_id' => $roleTecnico->id,
        ]);
        $this->tecnico1->assignRole('tecnico');

        $this->tecnico2 = User::factory()->create([
            'departamento_id' => $this->deptB->id,
            'role_id' => $roleTecnico->id,
        ]);
        $this->tecnico2->assignRole('tecnico');

        $this->especieOficio = DocumentoEspecie::firstOrCreate(
            ['nome' => 'Ofício'],
            ['ativo' => true]
        );
    }

    public function test_vinculo_criado_bidirecionalmente_entre_entrada_e_interno()
    {
        // 1. Criar Documento de Entrada
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 1001,
            'ano_referencia' => 2026,
            'assunto' => 'Solicitação de Parecer Técnico sobre Obra Pública',
            'procedencia' => 'Ministério das Obras Públicas',
            'data_entrada' => now(),
            'status' => 'pendente_tratamento',
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
        ]);

        // 2. Criar Documento Interno
        $interno = DocumentoInterno::create([
            'titulo' => 'Parecer Técnico nº 01/2026',
            'conteudo_final' => '<p>Conteúdo do parecer...</p>',
            'departamento_id' => $this->deptA->id,
            'criado_por' => $this->tecnico1->id,
            'status' => DocumentoStatus::APROVADO->value,
            'numero_referencia' => 'PAR.01/DRH/2026',
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        // 3. Vincular via Service
        $service = app(DocumentoVinculoService::class);
        $service->vincular(
            'EXTERNO',
            $entrada->id,
            [['tipo' => 'INTERNO', 'id' => $interno->id]],
            'INSTRUCAO_TECNICA',
            'Parecer emitido para subsidiar a resposta ao ministério',
            $this->admin
        );

        // 4. Testar listagem a partir da Entrada
        $vinculosEntrada = $service->listarVinculos('EXTERNO', $entrada->id, $this->admin);
        $this->assertCount(1, $vinculosEntrada);
        $this->assertEquals('INSTRUCAO_TECNICA', $vinculosEntrada[0]['tipo_relacao']);
        $this->assertEquals('Instrução / Parecer Técnico', $vinculosEntrada[0]['tipo_relacao_label']);
        $this->assertEquals($interno->id, $vinculosEntrada[0]['documento']['id']);
        $this->assertEquals('INTERNO', $vinculosEntrada[0]['documento']['tipo']);

        // 5. Testar listagem a partir do Documento Interno (Bidirecionalidade)
        $vinculosInterno = $service->listarVinculos('INTERNO', $interno->id, $this->admin);
        $this->assertCount(1, $vinculosInterno);
        $this->assertEquals('INSTRUCAO_TECNICA', $vinculosInterno[0]['tipo_relacao']);
        $this->assertEquals($entrada->id, $vinculosInterno[0]['documento']['id']);
        $this->assertEquals('EXTERNO', $vinculosInterno[0]['documento']['tipo']);
    }

    public function test_vinculo_criado_entre_duas_entradas_e_dois_internos()
    {
        // Duas entradas
        $entrada1 = DocumentoEntrada::create([
            'numero_sequencial' => 2001,
            'ano_referencia' => 2026,
            'assunto' => 'Processo Principal',
            'procedencia' => 'Secretaria Geral',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'em_andamento',
        ]);

        $entrada2 = DocumentoEntrada::create([
            'numero_sequencial' => 2002,
            'ano_referencia' => 2026,
            'assunto' => 'Processo Apenso',
            'procedencia' => 'Gabinete Municipal',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'em_andamento',
        ]);

        // Dois internos
        $interno1 = DocumentoInterno::create([
            'titulo' => 'Informação Técnica',
            'conteudo_final' => '<p>Info...</p>',
            'departamento_id' => $this->deptA->id,
            'criado_por' => $this->tecnico1->id,
            'status' => DocumentoStatus::APROVADO->value,
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        $interno2 = DocumentoInterno::create([
            'titulo' => 'Ofício de Devolução',
            'conteudo_final' => '<p>Ofício...</p>',
            'departamento_id' => $this->deptA->id,
            'criado_por' => $this->tecnico1->id,
            'status' => DocumentoStatus::ASSINADO->value,
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        $service = app(DocumentoVinculoService::class);

        // Entrada1 vinculada com Entrada2 (APENSO_ANEXO), com Interno1 (INSTRUCAO_TECNICA) e com Interno2 (RESPOSTA)
        $service->vincular(
            'EXTERNO',
            $entrada1->id,
            [
                ['tipo' => 'EXTERNO', 'id' => $entrada2->id],
                ['tipo' => 'INTERNO', 'id' => $interno1->id],
                ['tipo' => 'INTERNO', 'id' => $interno2->id],
            ],
            'COMPLEMENTAR',
            'Vínculo em lote do dossiê',
            $this->admin
        );

        $vinculos = $service->listarVinculos('EXTERNO', $entrada1->id, $this->admin);
        $this->assertCount(3, $vinculos);
    }

    public function test_api_vincular_multiplos_documentos()
    {
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 3001,
            'ano_referencia' => 2026,
            'assunto' => 'Entrada para Vínculo API',
            'procedencia' => 'Procuradoria Geral',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        $interno1 = DocumentoInterno::create([
            'titulo' => 'Doc Interno 1',
            'conteudo_final' => '<p>Teste</p>',
            'departamento_id' => $this->deptA->id,
            'criado_por' => $this->admin->id,
            'status' => DocumentoStatus::APROVADO->value,
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('api.documentos.vinculos.store', ['tipo' => 'EXTERNO', 'id' => $entrada->id]),
            [
                'documentos' => [
                    ['tipo' => 'INTERNO', 'id' => $interno1->id],
                ],
                'tipo_relacao' => 'RESPOSTA',
                'justificativa' => 'Ofício de resposta formal via API',
            ]
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('documento_vinculos', [
            'origem_tipo' => 'EXTERNO',
            'origem_id' => $entrada->id,
            'destino_tipo' => 'INTERNO',
            'destino_id' => $interno1->id,
            'tipo_relacao' => 'RESPOSTA',
        ]);
    }

    public function test_api_desvincular_documento()
    {
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 4001,
            'ano_referencia' => 2026,
            'assunto' => 'Entrada para Desvincular',
            'procedencia' => 'Decreto Presidencial',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        $interno = DocumentoInterno::create([
            'titulo' => 'Doc Interno Desvincular',
            'conteudo_final' => '<p>Teste</p>',
            'departamento_id' => $this->deptA->id,
            'criado_por' => $this->admin->id,
            'status' => DocumentoStatus::APROVADO->value,
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        $vinculo = DocumentoVinculo::create([
            'origem_tipo' => 'EXTERNO',
            'origem_id' => $entrada->id,
            'destino_tipo' => 'INTERNO',
            'destino_id' => $interno->id,
            'tipo_relacao' => 'RESPOSTA',
            'vinculado_por_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            route('api.documentos.vinculos.destroy', ['vinculo_id' => $vinculo->id])
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('documento_vinculos', [
            'id' => $vinculo->id,
        ]);
    }

    public function test_api_pesquisar_documentos_para_vincular()
    {
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 5001,
            'ano_referencia' => 2026,
            'assunto' => 'Ofício Requisitório de Auditoria Financeira',
            'procedencia' => 'Tribunal de Contas',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('api.documentos.vinculos.pesquisar', ['tipo' => 'EXTERNO', 'id' => 9999, 'q' => 'Auditoria Financeira'])
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonFragment(['numero_identificador' => '5001/2026']);
    }

    public function test_respeito_ao_rbac_na_visualizacao_de_documentos_vinculados()
    {
        // Entrada do Depto A
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 6001,
            'ano_referencia' => 2026,
            'assunto' => 'Entrada Depto A',
            'procedencia' => 'Governo Provincial',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        // Rascunho sigiloso do Tecnico 2 no Depto B
        $rascunhoDeptB = DocumentoInterno::create([
            'titulo' => 'Minuta Confidencial Depto B',
            'conteudo_final' => '<p>Segredo</p>',
            'departamento_id' => $this->deptB->id,
            'criado_por' => $this->tecnico2->id,
            'status' => DocumentoStatus::RASCUNHO->value,
            'documento_especie_id' => $this->especieOficio->id,
        ]);

        // Criar vínculo
        DocumentoVinculo::create([
            'origem_tipo' => 'EXTERNO',
            'origem_id' => $entrada->id,
            'destino_tipo' => 'INTERNO',
            'destino_id' => $rascunhoDeptB->id,
            'tipo_relacao' => 'COMPLEMENTAR',
            'vinculado_por_id' => $this->tecnico2->id,
        ]);

        $service = app(DocumentoVinculoService::class);

        // Técnico 1 (Depto A) consulta os vínculos da Entrada
        $vinculosTecnico1 = $service->listarVinculos('EXTERNO', $entrada->id, $this->tecnico1);
        $this->assertCount(1, $vinculosTecnico1);
        // O Técnico 1 NÃO tem permissão de ler o rascunho de outro técnico do Depto B
        $this->assertFalse($vinculosTecnico1[0]['documento']['pode_visualizar']);

        // Técnico 2 (autor do rascunho) consulta e TEM permissão
        $vinculosTecnico2 = $service->listarVinculos('EXTERNO', $entrada->id, $this->tecnico2);
        $this->assertCount(1, $vinculosTecnico2);
        $this->assertTrue($vinculosTecnico2[0]['documento']['pode_visualizar']);
    }

    public function test_elaborar_resposta_cria_documento_interno_e_persiste_vinculo()
    {
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 7001,
            'ano_referencia' => 2026,
            'assunto' => 'Solicitação de Férias Coletivas',
            'procedencia' => 'Gabinete Municipal',
            'data_entrada' => now(),
            'departamento_id' => $this->deptA->id,
            'user_id' => $this->admin->id,
            'status' => 'pendente_tratamento',
        ]);

        $response = $this->actingAs($this->chefeDept)->post(
            route('documentos-internos.store'),
            [
                'titulo' => 'Re: Solicitação de Férias Coletivas',
                'documento_especie_id' => $this->especieOficio->id,
                'documento_entrada_id' => $entrada->id,
                'tipo_relacao' => 'RESPOSTA',
                'vinculo_justificativa' => 'Parecer favorável à solicitação',
                'conteudo_final' => '<p>Deferido conforme solicitado.</p>',
            ]
        );

        $response->assertRedirect(route('documentos-internos.index'));

        $novoInterno = DocumentoInterno::where('titulo', 'Re: Solicitação de Férias Coletivas')->first();
        $this->assertNotNull($novoInterno);
        $this->assertEquals($entrada->id, $novoInterno->documento_entrada_id);

        $this->assertDatabaseHas('documento_vinculos', [
            'origem_tipo' => 'EXTERNO',
            'origem_id' => $entrada->id,
            'destino_tipo' => 'INTERNO',
            'destino_id' => $novoInterno->id,
            'tipo_relacao' => 'RESPOSTA',
        ]);
    }
}
