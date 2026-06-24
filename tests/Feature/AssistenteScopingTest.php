<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\Ai\DocumentoAssistantService;
use App\Services\Ai\FakeLlmClient;
use App\Services\DocumentoPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante o princípio "RAG com permissões primeiro": o assistente só recupera e cita
 * documentos que o utilizador já pode ver, segundo as regras de departamento/gabinete.
 */
class AssistenteScopingTest extends TestCase
{
    use RefreshDatabase;

    private const ALVO = 'ALVOSCOPING';

    protected DocumentoEspecie $especie;

    protected Gabinete $gab;

    protected Departamento $depA;

    protected Departamento $depB;

    protected User $userA;

    protected User $userB;

    protected DocumentoEntrada $entradaA;

    protected DocumentoEntrada $entradaB;

    protected DocumentoInterno $internoA;

    protected DocumentoInterno $internoB;

    protected DocumentoAssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->especie = DocumentoEspecie::create(['nome' => 'OFICIO', 'descricao' => 'Ofício', 'ativo' => true]);
        $this->gab = Gabinete::create(['nome' => 'Gabinete Geral', 'sigla' => 'GG']);
        $this->depA = Departamento::create(['nome' => 'Departamento A', 'sigla' => 'DA', 'gabinete_id' => $this->gab->id]);
        $this->depB = Departamento::create(['nome' => 'Departamento B', 'sigla' => 'DB', 'gabinete_id' => $this->gab->id]);

        $this->userA = User::factory()->create(['departamento_id' => $this->depA->id]);
        $this->userB = User::factory()->create(['departamento_id' => $this->depB->id]);

        $this->entradaA = $this->makeEntrada($this->depA, $this->userA, 10, 'Pedido '.self::ALVO.' do setor A');
        $this->entradaB = $this->makeEntrada($this->depB, $this->userB, 11, 'Pedido '.self::ALVO.' do setor B');

        $this->internoA = $this->makeInterno($this->depA, $this->userA, 'IA', 'Nota '.self::ALVO.' A');
        $this->internoB = $this->makeInterno($this->depB, $this->userB, 'IB', 'Nota '.self::ALVO.' B');

        // Serviço com IA fake (determinística, sem rede/custo).
        $this->service = new DocumentoAssistantService(new FakeLlmClient, app(DocumentoPermissionService::class));
    }

    private function makeEntrada(Departamento $dep, User $owner, int $seq, string $assunto): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => $assunto,
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);
    }

    private function makeInterno(Departamento $dep, User $owner, string $ref, string $titulo): DocumentoInterno
    {
        return DocumentoInterno::create([
            'titulo' => $titulo,
            'conteudo_final' => 'Conteúdo de teste',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $owner->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => $ref,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function fonteUrls(array $result): array
    {
        return array_map(fn ($f) => $f['url'], $result['fontes']);
    }

    public function test_utilizador_so_recupera_documentos_do_proprio_departamento(): void
    {
        $urls = $this->fonteUrls($this->service->askGlobal($this->userA, self::ALVO));

        $this->assertContains(route('documentos-entradas.show', $this->entradaA->id), $urls);
        $this->assertContains(route('documentos-internos.show', $this->internoA->id), $urls);

        // NÃO deve recuperar documentos do departamento B.
        $this->assertNotContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertNotContains(route('documentos-internos.show', $this->internoB->id), $urls);
    }

    public function test_outro_departamento_ve_apenas_os_seus(): void
    {
        $urls = $this->fonteUrls($this->service->askGlobal($this->userB, self::ALVO));

        $this->assertContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertNotContains(route('documentos-entradas.show', $this->entradaA->id), $urls);
        $this->assertNotContains(route('documentos-internos.show', $this->internoA->id), $urls);
    }

    public function test_utilizador_sem_departamento_nem_gabinete_nao_recupera_nada(): void
    {
        $semAcesso = User::factory()->create(['departamento_id' => null]);

        $result = $this->service->askGlobal($semAcesso, self::ALVO);

        $this->assertEmpty($result['fontes']);
    }

    public function test_admin_recupera_documentos_de_todos_os_departamentos(): void
    {
        $legacyAdmin = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $legacyAdmin->id, 'departamento_id' => null]);
        $admin->assignRole('admin');

        $urls = $this->fonteUrls($this->service->askGlobal($admin, self::ALVO));

        $this->assertContains(route('documentos-entradas.show', $this->entradaA->id), $urls);
        $this->assertContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertContains(route('documentos-internos.show', $this->internoB->id), $urls);
    }

    public function test_nao_pode_perguntar_sobre_entrada_de_outro_departamento(): void
    {
        $this->userA->givePermissionTo('assistente.usar');

        $this->actingAs($this->userA)
            ->postJson(route('assistente.entrada', $this->entradaB), ['pergunta' => 'Qual é o teor?'])
            ->assertStatus(403);
    }

    public function test_nao_pode_perguntar_sobre_interno_de_outro_departamento(): void
    {
        $this->userA->givePermissionTo('assistente.usar');

        $this->actingAs($this->userA)
            ->postJson(route('assistente.interno', $this->internoB), ['pergunta' => 'Resuma'])
            ->assertStatus(403);
    }

    public function test_pode_perguntar_sobre_documento_do_proprio_departamento(): void
    {
        $this->userA->givePermissionTo('assistente.usar');

        $this->actingAs($this->userA)
            ->postJson(route('assistente.entrada', $this->entradaA), ['pergunta' => 'Qual é o assunto?'])
            ->assertStatus(200)
            ->assertJsonStructure(['resposta', 'fontes']);
    }

    public function test_sem_permissao_assistente_e_proibido(): void
    {
        // userA não recebe a permissão assistente.usar.
        $this->actingAs($this->userA)
            ->postJson(route('assistente.perguntar'), ['pergunta' => 'Olá'])
            ->assertStatus(403);
    }
}
