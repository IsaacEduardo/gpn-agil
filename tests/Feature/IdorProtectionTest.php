<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    public function test_user_cannot_view_documento_entrada_from_other_department()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);

        $depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);

        $userA = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depA->id]);
        $userB = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depB->id]);

        $docA = DocumentoEntrada::create([
            'numero_sequencial' => 101,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Confidencial Departamento A',
            'departamento_id' => $depA->id,
            'user_id' => $userA->id,
            'status' => 'registrado',
            'origem' => 'externo',
        ]);

        // User B from Dept B attempts to view Document of Dept A
        $response = $this->actingAs($userB)->get(route('documentos-entradas.show', $docA));

        $response->assertStatus(403);
    }

    public function test_user_cannot_edit_documento_entrada_from_other_department()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);

        $depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);

        $userA = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depA->id]);
        $userB = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depB->id]);

        $docA = DocumentoEntrada::create([
            'numero_sequencial' => 102,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento A',
            'departamento_id' => $depA->id,
            'user_id' => $userA->id,
            'status' => 'registrado',
            'origem' => 'externo',
        ]);

        $response = $this->actingAs($userB)->get(route('documentos-entradas.edit', $docA));
        $response->assertStatus(403);
    }

    public function test_user_cannot_download_unauthorized_documento_interno_pdf()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $especie = DocumentoEspecie::first() ?? DocumentoEspecie::create(['nome' => 'Ofício Teste', 'ativo' => true]);

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);

        $depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);

        $userA = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depA->id]);
        $userB = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depB->id]);

        $docInterno = DocumentoInterno::create([
            'numero_referencia' => 'DESP/001/2026',
            'titulo' => 'Despacho Interno Reservado',
            'conteudo_final' => '<p>Conteúdo confidencial</p>',
            'departamento_id' => $depA->id,
            'criado_por' => $userA->id,
            'documento_especie_id' => $especie->id,
            'status' => DocumentoStatus::RASCUNHO,
        ]);

        $response = $this->actingAs($userB)->get(route('documentos-internos.pdf', $docInterno));
        $response->assertStatus(403);
    }

    public function test_user_cannot_update_or_delete_pasta_from_other_department()
    {
        $userRole = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);

        $depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);

        $userA = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depA->id]);
        $userB = User::factory()->create(['role_id' => $userRole->id, 'departamento_id' => $depB->id]);

        $pastaA = Pasta::create([
            'nome' => 'Pasta Privada A',
            'departamento_id' => $depA->id,
            'gabinete_id' => $gabA->id,
            'created_by' => $userA->id,
            'type' => 'custom',
            'is_system' => false,
        ]);

        $responseUpdate = $this->actingAs($userB)->put(route('pastas.update', $pastaA->id), [
            'nome' => 'Pasta Hackeada',
        ]);
        $responseUpdate->assertStatus(403);

        $responseDelete = $this->actingAs($userB)->delete(route('pastas.destroy', $pastaA->id));
        $responseDelete->assertStatus(403);
    }
}
