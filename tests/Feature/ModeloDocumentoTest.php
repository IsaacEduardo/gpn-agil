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

class ModeloDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_modelo_documento_with_dynamic_fields()
    {
        $user = User::factory()->create();

        $especie = DocumentoEspecie::firstOrCreate([
            'nome' => 'Parecer',
        ], [
            'ativo' => true,
        ]);

        $camposDinamicosJson = json_encode([
            'destino' => 'text',
            'motivo' => 'textarea',
            'dias_gozo' => 'number',
            'decisao' => 'select:Aprovado,Rejeitado',
        ]);

        $response = $this->actingAs($user)->post(route('modelos.store'), [
            'nome' => 'Modelo Teste Campos Dinâmicos',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Conteúdo {{DESTINO}} {{MOTIVO}} {{DIAS_GOZO}} {{DECISAO}}</p>',
            'campos_dinamicos_json' => $camposDinamicosJson,
            'ativo' => 1,
        ]);

        $response->assertRedirect(route('modelos.index'));

        $this->assertDatabaseHas('modelo_documentos', [
            'nome' => 'Modelo Teste Campos Dinâmicos',
            'documento_especie_id' => $especie->id,
        ]);

        $modelo = ModeloDocumento::where('nome', 'Modelo Teste Campos Dinâmicos')->first();
        $this->assertNotNull($modelo->campos_dinamicos);
        $this->assertEquals('text', $modelo->campos_dinamicos['destino']);
        $this->assertEquals('textarea', $modelo->campos_dinamicos['motivo']);
        $this->assertEquals('number', $modelo->campos_dinamicos['dias_gozo']);
        $this->assertEquals('select:Aprovado,Rejeitado', $modelo->campos_dinamicos['decisao']);
    }

    public function test_can_update_modelo_documento_with_dynamic_fields()
    {
        $user = User::factory()->create();
        $especie = DocumentoEspecie::firstOrCreate([
            'nome' => 'Despacho',
        ], [
            'ativo' => true,
        ]);

        $modelo = ModeloDocumento::create([
            'nome' => 'Modelo Antigo',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Corpo</p>',
            'ativo' => true,
            'user_id' => $user->id,
        ]);

        $camposDinamicosJson = json_encode([
            'observacoes' => 'textarea',
        ]);

        $response = $this->actingAs($user)->put(route('modelos.update', $modelo), [
            'nome' => 'Modelo Atualizado',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Corpo {{OBSERVACOES}}</p>',
            'campos_dinamicos_json' => $camposDinamicosJson,
            'ativo' => 1,
        ]);

        $response->assertRedirect(route('modelos.index'));

        $modelo->refresh();
        $this->assertEquals('Modelo Atualizado', $modelo->nome);
        $this->assertNotNull($modelo->campos_dinamicos);
        $this->assertEquals('textarea', $modelo->campos_dinamicos['observacoes']);
    }

    public function test_documento_interno_service_processes_custom_dynamic_fields()
    {
        $gabinete = Gabinete::create([
            'nome' => 'Gabinete de Tecnologia',
            'sigla' => 'GAB.TEC',
        ]);
        $departamento = Departamento::create([
            'nome' => 'Desenvolvimento',
            'sigla' => 'DEV',
            'gabinete_id' => $gabinete->id,
        ]);
        $user = User::factory()->create([
            'departamento_id' => $departamento->id,
        ]);

        $template = '<p>Destino: {{DESTINO}}, Decisão: {{DECISAO}}</p>';
        $dadosExtras = [
            'destino' => 'Luanda',
            'decisao' => 'Aprovado',
        ];

        $service = new DocumentoInternoService;
        $resultado = $service->processarTemplate($template, null, $user, $dadosExtras);

        $this->assertStringContainsString('Destino: Luanda', $resultado);
        $this->assertStringContainsString('Decisão: Aprovado', $resultado);
    }
}
