<?php

namespace Tests\Unit;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    use RefreshDatabase;

    private SignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SignatureService;
    }

    private function makeChefe(Departamento $dep): User
    {
        $user = User::factory()->create([
            'departamento_id' => $dep->id,
            'password' => Hash::make('senha-correta'),
        ]);
        $role = \Spatie\Permission\Models\Role::findOrCreate('chefe-departamento', 'web');
        $user->assignRole($role);

        return $user;
    }

    public function test_sign_sem_certificado_regista_visto_eletronico(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $especie = DocumentoEspecie::factory()->create(['nome' => 'NOTA']);

        $doc = DocumentoInterno::factory()->aprovado()->create([
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
        ]);

        $versaoAntes = $doc->versao_major;

        $this->service->sign($doc, $chefe, 'senha-correta');
        $doc->refresh();

        $this->assertStringStartsWith(SignatureService::VISTO_PREFIX, $doc->assinatura_hash);
        $this->assertEquals(DocumentoStatus::ASSINADO, $doc->status);
        $this->assertTrue((bool) $doc->bloqueado_edicao);
        $this->assertEquals($chefe->id, $doc->assinado_por_user_id);
        $this->assertEquals($versaoAntes + 1, $doc->versao_major);
    }

    public function test_sign_exige_documento_aprovado(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $especie = DocumentoEspecie::factory()->create(['nome' => 'NOTA']);

        $doc = DocumentoInterno::factory()->create([
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => DocumentoStatus::RASCUNHO,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->sign($doc, $chefe, 'senha-correta');
    }

    public function test_sign_valida_senha_do_utilizador(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $especie = DocumentoEspecie::factory()->create(['nome' => 'NOTA']);

        $doc = DocumentoInterno::factory()->aprovado()->create([
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
        ]);

        try {
            $this->service->sign($doc, $chefe, 'senha-errada');
            $this->fail('Deveria falhar com senha incorreta.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        $this->assertNull($doc->fresh()->assinado_em);
    }

    public function test_parecer_so_pode_ser_assinado_pelo_autor(): void
    {
        $dep = Departamento::factory()->create();
        $autor = $this->makeChefe($dep);
        $outro = $this->makeChefe($dep);
        $especie = DocumentoEspecie::factory()->create(['nome' => 'PARECER']);

        $doc = DocumentoInterno::factory()->aprovado()->create([
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'criado_por' => $autor->id,
        ]);

        $this->assertTrue($this->service->canSign($doc, $autor));
        $this->assertFalse($this->service->canSign($doc, $outro));
    }

    public function test_documento_ja_assinado_nao_pode_ser_reassinado(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $especie = DocumentoEspecie::factory()->create(['nome' => 'NOTA']);

        $doc = DocumentoInterno::factory()->aprovado()->create([
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'assinado_em' => now(),
        ]);

        $this->assertFalse($this->service->canSign($doc, $chefe));
    }

    public function test_batch_sign_assina_requisicoes_sem_certificado(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $requisitante = User::factory()->create(['departamento_id' => $dep->id]);

        $reqs = Requisicao::factory()->count(2)->create(['usuario_id' => $requisitante->id]);

        $count = $this->service->batchSign(
            $reqs->pluck('id')->all(),
            Requisicao::class,
            $chefe,
            'senha-correta'
        );

        $this->assertEquals(2, $count);
        foreach ($reqs as $req) {
            $req->refresh();
            $this->assertNotNull($req->assinado_em);
            $this->assertStringStartsWith(SignatureService::VISTO_PREFIX, $req->assinatura_hash);
        }
    }

    public function test_batch_sign_com_senha_errada_nao_assina_nada(): void
    {
        $dep = Departamento::factory()->create();
        $chefe = $this->makeChefe($dep);
        $requisitante = User::factory()->create(['departamento_id' => $dep->id]);

        $req = Requisicao::factory()->create(['usuario_id' => $requisitante->id]);

        try {
            $this->service->batchSign([$req->id], Requisicao::class, $chefe, 'senha-errada');
            $this->fail('Deveria falhar com senha incorreta.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        $this->assertNull($req->fresh()->assinado_em);
    }
}
