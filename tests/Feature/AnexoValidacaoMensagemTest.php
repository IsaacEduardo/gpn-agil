<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDocumentoEntradaRequest;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * As mensagens de recusa de anexo são lidas pelo balcão, não por programadores.
 *
 * Ao submeter um .txt, a mensagem era "O campo anexos.0 deve ser um arquivo do
 * tipo: pdf, jpg, jpeg, png" — o nome técnico do campo, com o índice do array.
 *
 * Cobre também o limite de tamanho, que a automação de teste não conseguiu
 * exercitar por impor um teto de 10 MB por transferência.
 */
class AnexoValidacaoMensagemTest extends TestCase
{
    use RefreshDatabase;

    private function balcao(): User
    {
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gab = Gabinete::create(['nome' => 'Gabinete X', 'sigla' => 'GX']);
        $dep = Departamento::create(['nome' => 'Dep X', 'gabinete_id' => $gab->id]);
        DocumentoEspecie::firstOrCreate(
            ['nome' => 'OFICIO'],
            ['descricao' => 'Ofício', 'ativo' => true, 'ordem' => 1]
        );

        $user = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $dep->id]);
        $user->syncRoles(['admin']);

        return $user->fresh();
    }

    private function payload(array $extra = []): array
    {
        $dep = Departamento::first();

        return array_merge([
            'classificacao_especie' => 'OFICIO',
            'assunto' => 'Assunto de teste',
            'departamento_id' => $dep->id,
        ], $extra);
    }

    public function test_tipo_recusado_nao_expoe_o_nome_tecnico_do_campo(): void
    {
        Storage::fake('public');
        $user = $this->balcao();

        $resposta = $this->actingAs($user)->post(
            route('documentos-entradas.store'),
            $this->payload(['anexos' => [UploadedFile::fake()->create('notas.txt', 12, 'text/plain')]])
        );

        $resposta->assertSessionHasErrors('anexos.0');

        $erro = session('errors')->first('anexos.0');

        $this->assertStringNotContainsString('anexos.0', $erro, 'A mensagem expõe o nome técnico do campo.');
        $this->assertSame('O ficheiro anexado deve ser PDF, JPG ou PNG.', $erro);
    }

    /**
     * O limite anunciado pela interface tem de ser o que o servidor aplica, e a
     * recusa tem de dizê-lo em linguagem corrente.
     */
    public function test_ficheiro_acima_do_limite_e_recusado_com_mensagem_legivel(): void
    {
        Storage::fake('public');
        $user = $this->balcao();

        $kbAcimaDoLimite = StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB + 2048;

        $resposta = $this->actingAs($user)->post(
            route('documentos-entradas.store'),
            $this->payload(['anexos' => [UploadedFile::fake()->create('grande.pdf', $kbAcimaDoLimite, 'application/pdf')]])
        );

        $resposta->assertSessionHasErrors('anexos.0');

        $erro = session('errors')->first('anexos.0');

        $this->assertStringNotContainsString('anexos.0', $erro);
        $this->assertStringContainsString((string) StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB, $erro);
    }

    public function test_pdf_dentro_do_limite_e_aceite(): void
    {
        Storage::fake('public');
        $user = $this->balcao();

        $resposta = $this->actingAs($user)->post(
            route('documentos-entradas.store'),
            $this->payload(['anexos' => [UploadedFile::fake()->create('oficio.pdf', 64, 'application/pdf')]])
        );

        $resposta->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documentos_entradas', ['assunto' => 'Assunto de teste']);
    }
}
