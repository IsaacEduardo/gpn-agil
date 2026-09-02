<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardOperacionalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $chefeDept;
    protected User $tecnico;
    protected Departamento $dept;
    protected Gabinete $gabinete;
    protected DocumentoEspecie $especie;

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

        $this->dept = Departamento::create([
            'nome' => 'Departamento de Planeamento e Estatística',
            'sigla' => 'DPE',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Dr. Administrador Geral',
            'departamento_id' => $this->dept->id,
            'role_id' => $roleAdmin->id,
        ]);
        $this->admin->assignRole('admin');

        $this->chefeDept = User::factory()->create([
            'name' => 'Eng. Chefe do DPE',
            'departamento_id' => $this->dept->id,
            'role_id' => $roleChefe->id,
        ]);
        $this->chefeDept->assignRole('chefe-departamento');
        $this->dept->update(['responsavel_id' => $this->chefeDept->id]);

        $this->tecnico = User::factory()->create([
            'name' => 'Técnico Manuel António',
            'departamento_id' => $this->dept->id,
            'role_id' => $roleTecnico->id,
        ]);
        $this->tecnico->assignRole('tecnico');

        $this->especie = DocumentoEspecie::firstOrCreate(
            ['nome' => 'Parecer Técnico'],
            ['ativo' => true]
        );
    }

    public function test_dashboard_renderiza_corretamente_para_perfil_tecnico()
    {
        // 1. Criar uma entrada e uma tarefa para o técnico
        $entrada = DocumentoEntrada::create([
            'numero_sequencial' => 101,
            'ano_referencia' => 2026,
            'assunto' => 'Análise de Viabilidade Económica do Projecto',
            'procedencia' => 'Secretaria de Finanças',
            'data_entrada' => now(),
            'departamento_id' => $this->dept->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $entrada->id,
            'titulo' => 'Elaborar parecer económico preliminar',
            'descricao' => 'Verificar planilhas orçamentais e cronograma',
            'assigned_by_id' => $this->chefeDept->id,
            'assigned_to_user_id' => $this->tecnico->id,
            'prazo_at' => now()->addDays(3),
            'status' => 'pendente',
        ]);

        // 2. Criar um rascunho de documento interno pelo técnico
        $rascunho = DocumentoInterno::create([
            'titulo' => 'Minuta de Informação nº 12/2026',
            'conteudo_final' => '<p>Minuta inicial</p>',
            'departamento_id' => $this->dept->id,
            'criado_por' => $this->tecnico->id,
            'status' => DocumentoStatus::RASCUNHO->value,
            'documento_especie_id' => $this->especie->id,
        ]);

        // 3. Acessar o dashboard como técnico
        $response = $this->actingAs($this->tecnico)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Minha Área de Trabalho');
        $response->assertSee('Técnico Operacional');
        $response->assertSee('Minhas Tarefas Pendentes');
        $response->assertSee('Meus Rascunhos Internos');
        $response->assertSee('Minhas Demandas Imediatas');
        $response->assertSee('Elaborar parecer económico preliminar');
        $response->assertSee('Minuta de Informação nº 12/2026');
    }

    public function test_dashboard_renderiza_corretamente_para_chefe_departamento()
    {
        // 1. Criar entradas no departamento
        DocumentoEntrada::create([
            'numero_sequencial' => 202,
            'ano_referencia' => 2026,
            'assunto' => 'Pedido de Avaliação Cadastral',
            'procedencia' => 'Ministério do Urbanismo',
            'data_entrada' => now(),
            'departamento_id' => $this->dept->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        // 2. Criar documento submetido pela equipe para revisão
        DocumentoInterno::create([
            'titulo' => 'Parecer Conclusivo sobre Cadastro',
            'conteudo_final' => '<p>Parecer pronto</p>',
            'departamento_id' => $this->dept->id,
            'criado_por' => $this->tecnico->id,
            'status' => DocumentoStatus::EM_ANALISE->value,
            'documento_especie_id' => $this->especie->id,
        ]);

        // 3. Acessar dashboard como chefe de departamento
        $response = $this->actingAs($this->chefeDept)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Painel de Gestão Setorial');
        $response->assertSee('Chefia de Departamento');
        $response->assertSee('Novas Entradas no Setor');
        $response->assertSee('Minutas p/ Minha Revisão');
        $response->assertSee('Pedido de Avaliação Cadastral');
        $response->assertSee('Parecer Conclusivo sobre Cadastro');
    }

    public function test_dashboard_renderiza_corretamente_para_chefe_gabinete_e_admin()
    {
        // 1. Criar entradas pendentes de despacho executivo
        DocumentoEntrada::create([
            'numero_sequencial' => 303,
            'ano_referencia' => 2026,
            'assunto' => 'Recomendação Urgente do Tribunal de Contas',
            'procedencia' => 'Tribunal de Contas',
            'data_entrada' => now(),
            'departamento_id' => $this->dept->id,
            'user_id' => $this->admin->id,
            'status' => 'pendente_tratamento',
        ]);

        // 2. Criar ato emitido recente
        DocumentoInterno::create([
            'titulo' => 'Ordem de Serviço nº 05/GAB/2026',
            'conteudo_final' => '<p>Determina-se a abertura de processo...</p>',
            'departamento_id' => $this->dept->id,
            'criado_por' => $this->admin->id,
            'status' => DocumentoStatus::ASSINADO->value,
            'assinado_em' => now(),
            'documento_especie_id' => $this->especie->id,
        ]);

        // 3. Acessar dashboard como Admin
        $response = $this->actingAs($this->admin)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Painel Executivo & Governança');
        $response->assertSee('A Carecer de Despacho');
        $response->assertSee('Recomendação Urgente do Tribunal de Contas');
        $response->assertSee('Ordem de Serviço nº 05/GAB/2026');
    }

    public function test_endpoint_api_estatisticas_retorna_json_completo_para_cada_perfil()
    {
        // 1. Testar resposta da API para Técnico
        $resTecnico = $this->actingAs($this->tecnico)->getJson(route('api.dashboard.estatisticas'));
        $resTecnico->assertOk();
        $resTecnico->assertJsonStructure([
            'success',
            'timestamp',
            'data' => [
                'perfil',
                'banner' => ['titulo', 'saudacao', 'subtitulo'],
                'kpis',
                'listas' => [
                    'esquerda' => ['titulo', 'tipo', 'itens'],
                    'direita' => ['titulo', 'tipo', 'itens'],
                ],
            ],
        ]);
        $this->assertEquals('TECNICO', $resTecnico->json('data.perfil'));

        // 2. Testar resposta da API para Chefe de Departamento
        $resChefe = $this->actingAs($this->chefeDept)->getJson(route('api.dashboard.estatisticas'));
        $resChefe->assertOk();
        $this->assertEquals('CHEFE_DEPARTAMENTO', $resChefe->json('data.perfil'));

        // 3. Testar resposta da API para Administrador
        $resAdmin = $this->actingAs($this->admin)->getJson(route('api.dashboard.estatisticas'));
        $resAdmin->assertOk();
        $this->assertEquals('CHEFE_GABINETE_ADMIN', $resAdmin->json('data.perfil'));
    }

    public function test_caching_de_dashboard_otimiza_consultas()
    {
        $service = app(DashboardService::class);

        // Primeira chamada armazena em cache
        $data1 = $service->obterDadosDashboard($this->tecnico, true);
        $this->assertIsArray($data1);

        $cacheKey = "dashboard:v2:user:{$this->tecnico->id}:TECNICO";
        $this->assertTrue(Cache::has($cacheKey));

        // Segunda chamada recupera do cache
        $data2 = $service->obterDadosDashboard($this->tecnico, false);
        $this->assertEquals($data1['perfil'], $data2['perfil']);
        $this->assertEquals($data1['banner']['titulo'], $data2['banner']['titulo']);
    }

    public function test_aliases_de_rota_inicio_e_dashboard_respondem_com_sucesso()
    {
        $resInicio = $this->actingAs($this->tecnico)->get('/inicio');
        $resInicio->assertOk();
        $resInicio->assertSee('Minha Área de Trabalho');

        $resDashboard = $this->actingAs($this->tecnico)->get('/dashboard');
        $resDashboard->assertOk();
        $resDashboard->assertSee('Minha Área de Trabalho');
    }
}
