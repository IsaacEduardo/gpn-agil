<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Procedencia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 4 — integridade do registo no balcão.
 */
class DocumentoEntradaRegistoIntegridadeTest extends TestCase
{
    use RefreshDatabase;

    private User $balcao;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->dep->id]);
        $this->balcao->assignRole($roleUser);

        DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true, 'ordem' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
        ], $overrides);
    }

    /** F6 — espécie era required no HTML e nullable na validação. */
    public function test_especie_e_obrigatoria_no_servidor(): void
    {
        $payload = $this->payload();
        unset($payload['classificacao_especie']);

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $payload)
            ->assertSessionHasErrors('classificacao_especie');

        $this->assertDatabaseCount('documentos_entradas', 0);
    }

    /** F5 — data_entrada era sempre now(); correspondência em atraso ficava com data errada. */
    public function test_aceita_data_de_entrada_retroativa(): void
    {
        $ontem = now()->subDays(3)->startOfDay();

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'data_entrada' => $ontem->toDateString(),
            ]))
            ->assertRedirect();

        $doc = DocumentoEntrada::firstOrFail();
        $this->assertSame($ontem->toDateString(), $doc->data_entrada->toDateString());
    }

    public function test_recusa_data_de_entrada_no_futuro(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'data_entrada' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('data_entrada');

        $this->assertDatabaseCount('documentos_entradas', 0);
    }

    public function test_sem_data_de_entrada_usa_hoje(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload())
            ->assertRedirect();

        $doc = DocumentoEntrada::firstOrFail();
        $this->assertSame(now()->toDateString(), $doc->data_entrada->toDateString());
    }

    /** F4 — nada impedia registar duas vezes o mesmo ofício. */
    public function test_avisa_de_possivel_duplicado_e_nao_grava(): void
    {
        $proc = Procedencia::create(['nome' => 'Ministério das Finanças', 'ativo' => true]);

        $comum = [
            'procedencia_id' => $proc->id,
            'classificacao_ref_numero' => '123/2026',
            'data_documento' => '2026-09-01',
        ];

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload($comum))
            ->assertRedirect();

        $this->assertDatabaseCount('documentos_entradas', 1);

        // Segunda tentativa idêntica: avisa em vez de gravar.
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload($comum))
            ->assertSessionHasErrors('duplicado');

        $this->assertDatabaseCount('documentos_entradas', 1);
    }

    /** O balcão tem de continuar a poder registar uma segunda via legítima. */
    public function test_permite_duplicado_com_confirmacao_explicita(): void
    {
        $proc = Procedencia::create(['nome' => 'Ministério das Finanças', 'ativo' => true]);

        $comum = [
            'procedencia_id' => $proc->id,
            'classificacao_ref_numero' => '123/2026',
            'data_documento' => '2026-09-01',
        ];

        $this->actingAs($this->balcao)->post(route('documentos-entradas.store'), $this->payload($comum));

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload($comum + ['confirmar_duplicado' => '1']))
            ->assertRedirect();

        $this->assertDatabaseCount('documentos_entradas', 2);
    }

    /** Sem número de referência não há critério de duplicação — não avisa. */
    public function test_nao_avisa_quando_nao_ha_referencia_para_comparar(): void
    {
        $this->actingAs($this->balcao)->post(route('documentos-entradas.store'), $this->payload());
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseCount('documentos_entradas', 2);
    }

    /** Limites de ficheiro coerentes entre o campo principal e os anexos. */
    public function test_aceita_anexo_ate_dez_megabytes(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [UploadedFile::fake()->create('digitalizacao.pdf', 9000, 'application/pdf')],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('documentos_entradas', 1);
    }

    public function test_recusa_anexo_acima_do_limite(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [UploadedFile::fake()->create('enorme.pdf', 11000, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('anexos.0');
    }

    /** Um JPEG disfarçado de PDF não pode entrar no OCR nem no arquivo. */
    public function test_recusa_imagem_com_extensao_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-disfarcado-');
        file_put_contents($path, hex2bin('ffd8ffe000104a46494600010100000100010000ffd9'));
        $ficheiroDisfarcado = new UploadedFile($path, 'digitalizacao.pdf', 'application/pdf', null, true);

        try {
            $this->actingAs($this->balcao)
                ->post(route('documentos-entradas.store'), $this->payload([
                    'anexos' => [$ficheiroDisfarcado],
                ]))
                ->assertSessionHasErrors('anexos.0');
        } finally {
            @unlink($path);
        }
    }
}
