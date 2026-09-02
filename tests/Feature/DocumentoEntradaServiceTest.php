<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoEntradaServiceTest extends TestCase
{
    // Use RefreshDatabase to reset DB after each test
    // WARNING: This clears the database. If you want to run against a real DB without clearing, remove this trait.
    // However, for reliable testing, it is recommended to use an in-memory SQLite DB or a separate test DB.
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentoEntradaService::class);
        Storage::fake('public'); // Mock storage
    }

    public function test_can_create_documento_entrada()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $gabinete = Gabinete::create(['nome' => 'Gabinete Teste', 'sigla' => 'GABTEST']);
        $departamento = Departamento::create(['nome' => 'Departamento Teste', 'gabinete_id' => $gabinete->id]);

        $data = [
            'classificacao_especie' => 'Ofício',
            'classificacao_ref_numero' => '123/2025',
            'data_documento' => now()->format('Y-m-d'),
            'procedencia' => 'Externo',
            'assunto' => 'Assunto de Teste',
            'observacoes' => 'Observações de teste',
            'departamento_id' => $departamento->id,
            'tags' => 'urgente, teste',
        ];

        $file = UploadedFile::fake()->create('documento.pdf', 100);

        // Act
        $doc = $this->service->createDocument($data, $file);

        // Assert
        $this->assertDatabaseHas('documentos_entradas', [
            'id' => $doc->id,
            'assunto' => 'Assunto de Teste',
            'numero_sequencial' => 1,
            'status' => \App\Enums\DocumentoStatus::PENDENTE_TRATAMENTO->value,
        ]);

        $this->assertNotNull($doc->arquivo_caminho);
        $this->assertDatabaseHas('documento_protocolos', ['documento_entrada_id' => $doc->id]);
        $this->assertTrue($doc->tags->contains('nome', 'urgente'));
    }

    public function test_can_filter_documents()
    {
        // Arrange
        $user = User::factory()->create();
        $gabinete = Gabinete::create(['nome' => 'Gab', 'sigla' => 'GAB', 'responsavel_id' => $user->id]);
        $dep1 = Departamento::create(['nome' => 'Dep 1', 'gabinete_id' => $gabinete->id]);
        $dep2 = Departamento::create(['nome' => 'Dep 2', 'gabinete_id' => $gabinete->id]);

        $user->update(['departamento_id' => $dep1->id]);
        \Illuminate\Support\Facades\Cache::forget("user_{$user->id}_responsible_gabinetes");
        \Illuminate\Support\Facades\Cache::forget("user_{$user->id}_departments");

        DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => 2025,
            'data_entrada' => now(),
            'assunto' => 'Documento Alpha',
            'departamento_id' => $dep1->id,
            'status' => 'registrado',
            'user_id' => $user->id,
        ]);

        DocumentoEntrada::create([
            'numero_sequencial' => 2,
            'ano_referencia' => 2025,
            'data_entrada' => now(),
            'assunto' => 'Documento Beta',
            'departamento_id' => $dep2->id,
            'status' => 'registrado',
            'user_id' => $user->id,
        ]);

        // Act: Filter by subject
        $request = new \Illuminate\Http\Request;
        $request->merge(['search' => 'Alpha']);

        $results = $this->service->getFilteredDocuments($request, $user);

        // Assert
        $this->assertEquals(1, $results->total());
        $this->assertEquals('Documento Alpha', $results->first()->assunto);

        // Act: Filter by Department
        $request2 = new \Illuminate\Http\Request;
        $request2->merge(['departamento_id' => $dep2->id]);

        $results2 = $this->service->getFilteredDocuments($request2, $user);

        // Assert
        $this->assertEquals(1, $results2->total());
        $this->assertEquals('Documento Beta', $results2->first()->assunto);
    }
}
