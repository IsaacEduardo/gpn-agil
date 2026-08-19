<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoInternoBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_selected_documents_as_zip()
    {
        $user = User::factory()->create();
        $gab = Gabinete::factory()->create();
        $dep = Departamento::factory()->create(['gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $doc1 = DocumentoInterno::create([
            'titulo' => 'Doc Lote 1',
            'conteudo_final' => '<p>Conteúdo Lote 1</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => 'rascunho',
            'numero_referencia' => 'OFI/001/2026',
            'versao_atual' => 1,
        ]);

        $doc2 = DocumentoInterno::create([
            'titulo' => 'Doc Lote 2',
            'conteudo_final' => '<p>Conteúdo Lote 2</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => 'rascunho',
            'numero_referencia' => 'OFI/002/2026',
            'versao_atual' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('documentos-internos.batch-zip'), [
            'documento_ids' => [$doc1->id, $doc2->id],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
    }
}
