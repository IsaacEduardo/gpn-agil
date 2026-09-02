<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\DocumentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdemDeServicoSecGeralTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_seeding_creates_ordem_de_servico_sec_geral()
    {
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ordem de Serviço'], ['sigla' => 'OS']);

        $modelo = ModeloDocumento::firstOrCreate(
            ['codigo' => 'ORDEM_DE_SERVICO_SEC_GERAL'],
            [
                'nome' => 'Ordem de Serviço (Exclusivo Secretaria Geral)',
                'documento_especie_id' => $especie->id,
                'conteudo' => '<p>ORDEM DE SERVIÇO {{numero_ordem}} / {{verbo_operativo}}</p>',
                'ativo' => true,
            ]
        );

        $this->assertDatabaseHas('modelo_documentos', [
            'codigo' => 'ORDEM_DE_SERVICO_SEC_GERAL',
            'nome' => 'Ordem de Serviço (Exclusivo Secretaria Geral)',
        ]);
    }

    public function test_secretaria_geral_user_can_access_template()
    {
        $gab = Gabinete::factory()->create([
            'nome' => 'Secretaria Geral do Governo Provincial',
            'sigla' => 'SEC_GERAL',
        ]);
        $dep = Departamento::factory()->create([
            'nome' => 'Gabinete do Secretário Geral',
            'sigla' => 'SEC_GERAL',
            'gabinete_id' => $gab->id,
        ]);
        $user = User::factory()->create(['departamento_id' => $dep->id]);

        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ordem de Serviço'], ['sigla' => 'OS']);
        $modelo = ModeloDocumento::firstOrCreate(
            ['codigo' => 'ORDEM_DE_SERVICO_SEC_GERAL'],
            [
                'nome' => 'Ordem de Serviço (Exclusivo Secretaria Geral)',
                'documento_especie_id' => $especie->id,
                'conteudo' => '<p>Ordem da Secretaria Geral</p>',
                'ativo' => true,
            ]
        );

        $service = app(DocumentoInternoService::class);
        $templates = $service->getTemplatesForUser($user);

        $this->assertTrue($templates->contains('id', $modelo->id));
    }

    public function test_other_departments_cannot_access_sec_geral_template()
    {
        $gabOther = Gabinete::factory()->create([
            'nome' => 'Gabinete de Infraestruturas',
            'sigla' => 'DINF',
        ]);
        $depOther = Departamento::factory()->create([
            'nome' => 'Departamento de Obras',
            'sigla' => 'DOBRAS',
            'gabinete_id' => $gabOther->id,
        ]);
        $userOther = User::factory()->create(['departamento_id' => $depOther->id]);

        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ordem de Serviço'], ['sigla' => 'OS']);
        $modelo = ModeloDocumento::firstOrCreate(
            ['codigo' => 'ORDEM_DE_SERVICO_SEC_GERAL'],
            [
                'nome' => 'Ordem de Serviço (Exclusivo Secretaria Geral)',
                'documento_especie_id' => $especie->id,
                'conteudo' => '<p>Ordem da Secretaria Geral</p>',
                'ativo' => true,
            ]
        );

        $service = app(DocumentoInternoService::class);
        $templates = $service->getTemplatesForUser($userOther);

        $this->assertFalse($templates->contains('id', $modelo->id));
    }

    public function test_placeholder_processing_for_ordem_de_servico()
    {
        $user = User::factory()->create(['name' => 'Anselmo Vasco']);
        $service = app(DocumentoInternoService::class);

        $template = '
            <div>
                <h1>ORDEM DE SERVIÇO Nº {{ numero_ordem }} /SEC.GER.GOV.PROV.HLA/{{ ano_corrente }}</h1>
                <p>{{ preambulo_motivo }}</p>
                <div>{{ verbo_operativo }}</div>
                <div>{{ texto_deliberacao }}</div>
                <div>{{ cargo_signatario }}</div>
                <div>{{ nome_signatario }}</div>
                <div>{{ portal_url }}</div>
            </div>
        ';

        $processed = $service->processarTemplate($template, null, $user, [
            'numero_ordem' => '08',
            'data_inicio_ausencia' => '2026-05-26',
            'substituto_nome' => 'Manuel Silva',
            'substituto_departamento' => 'Gestão do Orçamento e Contabilidade',
            'preambulo_motivo' => 'Ausentando-me para cumprimento de missão oficial...',
            'verbo_operativo' => 'DETERMINO:',
            'texto_deliberacao' => 'Designo o Dr. Manuel Silva para responder pelas funções.',
            'cargo_signatario' => 'O Secretário Geral',
            'nome_signatario' => 'Anselmo Cristiano José Vasco',
            'portal_url' => 'huila.gov.ao',
        ]);

        $this->assertStringContainsString('08', $processed);
        $this->assertStringContainsString('Ausentando-me para cumprimento de missão oficial...', $processed);
        $this->assertStringContainsString('DETERMINO:', $processed);
        $this->assertStringContainsString('Designo o Dr. Manuel Silva', $processed);
        $this->assertStringContainsString('O Secretário Geral', $processed);
        $this->assertStringContainsString('Anselmo Cristiano José Vasco', $processed);
        $this->assertStringContainsString('huila.gov.ao', $processed);
    }

    public function test_dynamic_absence_date_and_department_head_substitute_resolution()
    {
        $dep = Departamento::factory()->create(['nome' => 'Gestão de Recursos Humanos', 'sigla' => 'DRH']);
        $chefe = User::factory()->create([
            'name' => 'Dr. Fernando Agostinho',
            'departamento_id' => $dep->id,
        ]);
        $dep->update(['responsavel_id' => $chefe->id]);

        $secUser = User::factory()->create(['name' => 'Secretário Geral Huíla']);

        $service = app(DocumentoInternoService::class);
        $template = '<p>Ausentando-me a partir do dia {{ data_inicio_ausencia }}. O Senhor <strong>{{ substituto_nome }}</strong> - Chefe de Departamento de {{ substituto_departamento }}.</p>';

        $processed = $service->processarTemplate($template, null, $secUser, [
            'data_inicio_ausencia' => '2026-05-26',
            'substituto_user_id' => $chefe->id,
        ]);

        $this->assertStringContainsString('26 de maio de 2026', mb_strtolower($processed));
        $this->assertStringContainsString('Dr. Fernando Agostinho', $processed);
        $this->assertStringContainsString('Gestão de Recursos Humanos', $processed);
    }
}
