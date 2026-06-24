<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoRelacoesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $doc1;

    protected $doc2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $gabinete = Gabinete::create([
            'nome' => 'Gabinete Teste',
            'sigla' => 'GAB',
        ]);

        $departamento = Departamento::create([
            'nome' => 'Departamento Teste',
            'sigla' => 'DPT',
            'gabinete_id' => $gabinete->id,
        ]);

        $this->doc1 = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'assunto' => 'Documento de Entrada 1',
            'departamento_id' => $departamento->id,
            'status' => 'registrado',
            'user_id' => $this->user->id,
        ]);

        $this->doc2 = DocumentoEntrada::create([
            'numero_sequencial' => 2,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'assunto' => 'Documento de Entrada 2',
            'departamento_id' => $departamento->id,
            'status' => 'registrado',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_relate_two_documents()
    {
        $response = $this->actingAs($this->user)
            ->post(route('documentos-entradas.relacionar', $this->doc1), [
                'relacionado_id' => $this->doc2->id,
                'relacionado_type' => 'entrada',
                'tipo' => 'relacionado',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Documento relacionado com sucesso.');

        $this->assertDatabaseHas('documento_relacoes', [
            'documento_id' => $this->doc1->id,
            'relacionado_id' => $this->doc2->id,
            'tipo' => 'relacionado',
        ]);

        $this->assertCount(1, $this->doc1->fresh()->todos_relacionados);
        $this->assertCount(1, $this->doc2->fresh()->todos_relacionados);
    }

    public function test_cannot_relate_same_documents_twice_direct()
    {
        // Link doc1 -> doc2 first
        $this->doc1->documentosRelacionados()->attach($this->doc2->id, ['tipo' => 'relacionado']);

        // Try linking doc1 -> doc2 again via route
        $response = $this->actingAs($this->user)
            ->post(route('documentos-entradas.relacionar', $this->doc1), [
                'relacionado_id' => $this->doc2->id,
                'relacionado_type' => 'entrada',
                'tipo' => 'resposta',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('info', 'Documentos já estão relacionados.');

        // Assert only one relationship exists in db
        $this->assertEquals(1, \DB::table('documento_relacoes')->count());
    }

    public function test_cannot_relate_same_documents_twice_inverse()
    {
        // Link doc1 -> doc2 first
        $this->doc1->documentosRelacionados()->attach($this->doc2->id, ['tipo' => 'relacionado']);

        // Try linking doc2 -> doc1 (inverse) via route
        $response = $this->actingAs($this->user)
            ->post(route('documentos-entradas.relacionar', $this->doc2), [
                'relacionado_id' => $this->doc1->id,
                'relacionado_type' => 'entrada',
                'tipo' => 'resposta',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('info', 'Documentos já estão relacionados.');

        // Assert only one relationship exists in db (no inverse duplicate row)
        $this->assertEquals(1, \DB::table('documento_relacoes')->count());
    }
}
