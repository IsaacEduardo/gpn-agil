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

    public function test_get_templates_for_user_filters_inactive_and_gabinete()
    {
        $user = User::factory()->create();
        $gab = Gabinete::factory()->create();
        $dep = Departamento::factory()->create(['gabinete_id' => $gab->id]);
        $user->update(['departamento_id' => $dep->id]);

        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $activeModel = ModeloDocumento::create([
            'nome' => 'Modelo Ativo Gabinete',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Conteúdo {{USUARIO_NOME}}</p>',
            'gabinete_id' => $gab->id,
            'user_id' => $user->id,
            'ativo' => true,
        ]);

        $inactiveModel = ModeloDocumento::create([
            'nome' => 'Modelo Inativo Obsoleto',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Conteúdo antigo</p>',
            'gabinete_id' => $gab->id,
            'user_id' => $user->id,
            'ativo' => false,
        ]);

        $service = app(DocumentoInternoService::class);
        $templates = $service->getTemplatesForUser($user);

        $this->assertTrue($templates->contains('id', $activeModel->id));
        $this->assertFalse($templates->contains('id', $inactiveModel->id));
    }

    public function test_template_placeholders_processing()
    {
        $user = User::factory()->create(['name' => 'Carlos Silva']);
        $service = app(DocumentoInternoService::class);

        $rawTemplate = '<p>Elaborado por {{USUARIO_NOME}} em {{DATA_ATUAL}} para {{DESTINATARIO_NOME}}.</p>';
        $processed = $service->processarTemplate($rawTemplate, null, $user, [
            'destinatario_nome' => 'Manuel Bento',
        ]);

        $this->assertStringContainsString('Carlos Silva', $processed);
        $this->assertStringContainsString('Manuel Bento', $processed);
        $this->assertStringContainsString(now()->format('d/m/Y'), $processed);
    }

    public function test_modelo_show_preview_endpoint()
    {
        $user = User::factory()->create();
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $modelo = ModeloDocumento::create([
            'nome' => 'Modelo Preview Teste',
            'documento_especie_id' => $especie->id,
            'conteudo' => '<p>Template {{USUARIO_NOME}}</p>',
            'user_id' => $user->id,
            'ativo' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('modelos.show', ['modelo' => $modelo->id, 'preview' => 1]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['modelo', 'conteudo_processado']);
        $response->assertJsonFragment(['nome' => 'Modelo Preview Teste']);
    }
}
