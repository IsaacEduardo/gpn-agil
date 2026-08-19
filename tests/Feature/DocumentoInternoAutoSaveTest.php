<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoInternoAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_save_updates_existing_draft()
    {
        $user = User::factory()->create();
        $gab = Gabinete::factory()->create();
        $dep = Departamento::factory()->create(['gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $doc = DocumentoInterno::create([
            'titulo' => 'Rascunho Inicial',
            'conteudo_final' => '<p>Conteúdo antes do auto-save</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => 'rascunho',
            'numero_referencia' => 'OFI/999/2026',
            'versao_atual' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(route('documentos-internos.auto-save', $doc), [
            'titulo' => 'Rascunho Atualizado',
            'conteudo_final' => '<p>Conteúdo salvo automaticamente em background</p>',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'documento_id' => $doc->id,
        ]);

        $this->assertDatabaseHas('documento_internos', [
            'id' => $doc->id,
            'titulo' => 'Rascunho Atualizado',
            'conteudo_final' => '<p>Conteúdo salvo automaticamente em background</p>',
        ]);
    }
}
