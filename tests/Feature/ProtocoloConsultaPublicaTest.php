<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoProtocolo;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O QR do recibo apontava para uma rota dentro do grupo 'auth': o munícipe que
 * entrega o documento recebia um comprovativo que não conseguia abrir.
 *
 * A página pública tem de mostrar MENOS do que a interna — o P2 fechou a fuga
 * que expunha assunto, procedência e departamento a quem não devia. Aqui só há
 * código, data de entrada e estado.
 */
class ProtocoloConsultaPublicaTest extends TestCase
{
    use RefreshDatabase;

    private DocumentoEntrada $doc;

    private DocumentoProtocolo $protocolo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $papel = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Departamento Secreto', 'gabinete_id' => $gab->id]);
        $u = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $dep->id]);

        $this->doc = DocumentoEntrada::create([
            'numero_sequencial' => 42,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(2),
            'assunto' => 'Assunto confidencial que nao pode vazar',
            'procedencia' => 'Procedencia confidencial',
            'departamento_id' => $dep->id,
            'user_id' => $u->id,
            'status' => 'recebido',
        ]);

        $this->protocolo = DocumentoProtocolo::create([
            'documento_entrada_id' => $this->doc->id,
            'codigo' => 'PRT-2026-042-ABCDEF',
            'url_consulta' => 'http://exemplo/irrelevante',
            'gerado_em' => now(),
        ]);
    }

    public function test_consulta_publica_nao_exige_autenticacao(): void
    {
        $this->get(route('protocolo.publico', $this->protocolo->codigo))
            ->assertStatus(200)
            ->assertSee('PRT-2026-042-ABCDEF', false);
    }

    /** O essencial: a página pública não pode revelar o conteúdo do documento. */
    public function test_nao_revela_assunto_procedencia_nem_departamento(): void
    {
        $resposta = $this->get(route('protocolo.publico', $this->protocolo->codigo));

        $resposta->assertDontSee('Assunto confidencial que nao pode vazar', false);
        $resposta->assertDontSee('Procedencia confidencial', false);
        $resposta->assertDontSee('Departamento Secreto', false);
    }

    public function test_mostra_o_estado_em_linguagem_do_municipe(): void
    {
        $this->get(route('protocolo.publico', $this->protocolo->codigo))
            ->assertStatus(200)
            ->assertSee('Em tratamento', false);
    }

    public function test_codigo_inexistente_devolve_404(): void
    {
        $this->get(route('protocolo.publico', 'PRT-0000-000-XXXXXX'))->assertStatus(404);
    }

    /** O QR do recibo tem de apontar para a página pública. */
    public function test_o_protocolo_gerado_aponta_para_a_pagina_publica(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $dep = Departamento::first();
        $balcao = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $dep->id]);
        $balcao->assignRole($papel);

        $this->actingAs($balcao);
        Storage::fake('public');

        $novo = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Novo registo',
            'departamento_id' => $dep->id,
            'classificacao_especie' => 'Ofício',
        ]);

        $this->assertStringContainsString(
            '/protocolo/',
            $novo->protocolo->url_consulta,
            'A URL de consulta do protocolo deve apontar para a página pública.',
        );
    }

    /**
     * A regressão que motivou derivar o URL no momento de imprimir: o QR saía
     * do que estava gravado em `url_consulta`, e o que lá estava era o host da
     * máquina que fez o registo (127.0.0.1) — que num telemóvel aponta para o
     * próprio telemóvel.
     */
    public function test_a_etiqueta_ignora_a_url_gravada_e_usa_a_publica(): void
    {
        $resposta = $this->actingAs($this->operadorDoDepartamento())
            ->get(route('documentos-entradas.protocolo.etiqueta', $this->doc));

        $resposta->assertStatus(200);
        $this->assertSame(
            route('protocolo.publico', ['codigo' => 'PRT-2026-042-ABCDEF']),
            $resposta->viewData('consultaUrl'),
        );
    }

    /** O estado dos protocolos antigos: gravaram a rota interna, que pede login. */
    public function test_o_protocolo_antigo_deixa_de_apontar_para_a_rota_interna(): void
    {
        $this->protocolo->update([
            'url_consulta' => 'http://127.0.0.1:8000/documentos-entradas/'.$this->doc->id.'/protocolo',
        ]);

        $resposta = $this->actingAs($this->operadorDoDepartamento())
            ->get(route('documentos-entradas.protocolo', $this->doc));

        $resposta->assertStatus(200);
        $this->assertSame(
            route('protocolo.publico', ['codigo' => 'PRT-2026-042-ABCDEF']),
            $resposta->viewData('consultaUrl'),
        );
        $resposta->assertDontSee('127.0.0.1', false);
    }

    /** O que fica gravado é o caminho, sem host: continua verdadeiro se o domínio mudar. */
    public function test_o_registo_guarda_o_caminho_sem_host(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $dep = Departamento::first();
        $balcao = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $dep->id]);
        $balcao->assignRole($papel);

        $this->actingAs($balcao);
        Storage::fake('public');

        $novo = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Novo registo',
            'departamento_id' => $dep->id,
            'classificacao_especie' => 'Ofício',
        ]);

        $this->assertStringStartsWith('/protocolo/', $novo->protocolo->url_consulta);
    }

    private function operadorDoDepartamento(): User
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $operador = User::factory()->create([
            'role_id' => $papel->id,
            'departamento_id' => $this->doc->departamento_id,
        ]);
        $operador->assignRole($papel);

        return $operador;
    }
}
