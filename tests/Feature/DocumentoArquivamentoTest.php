<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoArquivamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_triggers_auto_archiving()
    {
        $user = User::factory()->create();
        $user->assignRole(\App\Enums\UserRole::ADMIN->value);

        $gab = Gabinete::factory()->create();
        $dep = Departamento::factory()->create(['gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $doc = DocumentoInterno::create([
            'titulo' => 'Doc para Assinar e Arquivar',
            'conteudo_final' => '<p>Conteúdo de teste</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OFI/777/2026',
            'versao_atual' => 1,
        ]);

        $signatureService = app(\App\Services\SignatureService::class);
        $signatureService->sign($doc, $user, '', true);

        $doc->refresh();
        $this->assertTrue((bool) $doc->arquivado);
        $this->assertNotNull($doc->arquivado_em);
        $this->assertEquals($user->id, $doc->arquivado_por);
    }

    public function test_purge_drafts_command_removes_obsolete_drafts()
    {
        $user = User::factory()->create();
        $gab = Gabinete::factory()->create();
        $dep = Departamento::factory()->create(['gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício Teste'], ['sigla' => 'OFI']);

        $oldDraft = DocumentoInterno::create([
            'titulo' => 'Rascunho Antigo Obsoleto',
            'conteudo_final' => '<p>Rascunho abandonado</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => \App\Enums\DocumentoStatus::RASCUNHO,
            'numero_referencia' => 'OFI/101/2026',
        ]);

        // Directly set updated_at in past
        \Illuminate\Support\Facades\DB::table('documento_internos')
            ->where('id', $oldDraft->id)
            ->update(['updated_at' => now()->subDays(40)]);

        $recentDraft = DocumentoInterno::create([
            'titulo' => 'Rascunho Recente',
            'conteudo_final' => '<p>Rascunho em edição</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => \App\Enums\DocumentoStatus::RASCUNHO,
            'numero_referencia' => 'OFI/102/2026',
        ]);

        $this->artisan('documents:purge-drafts --days=30')
             ->assertExitCode(0);

        $this->assertDatabaseMissing('documento_internos', ['id' => $oldDraft->id]);
        $this->assertDatabaseHas('documento_internos', ['id' => $recentDraft->id]);
    }
}
