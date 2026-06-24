<?php

namespace Tests\Feature;

use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Embeddings\FakeEmbeddingClient;
use App\Chatbot\Models\ChatbotChunk;
use App\Chatbot\Services\ChatbotRetriever;
use App\Chatbot\Services\DocumentChunker;
use App\Chatbot\Services\DocumentIndexer;
use App\Chatbot\VectorStore\MysqlVectorStore;
use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a busca semântica do chatbot: indexação, recuperação restrita por permissões
 * (utilizador A nunca recebe chunks/citações do dept. B) e a API REST.
 */
class ChatbotRagTest extends TestCase
{
    use RefreshDatabase;

    private const ALVO = 'ALVOSCOPING';

    protected DocumentoEspecie $especie;

    protected Departamento $depA;

    protected Departamento $depB;

    protected User $userA;

    protected User $userB;

    protected DocumentoEntrada $entradaA;

    protected DocumentoEntrada $entradaB;

    protected DocumentoInterno $internoA;

    protected DocumentoInterno $internoB;

    protected FakeEmbeddingClient $embeddings;

    protected DocumentIndexer $indexer;

    protected ChatbotRetriever $retriever;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.enabled' => true,
            'chatbot.vector_store.min_score' => 0.0,
        ]);

        $this->embeddings = new FakeEmbeddingClient(64);
        $this->app->instance(EmbeddingClient::class, $this->embeddings);

        $vector = new MysqlVectorStore(config('chatbot.vector_store'));
        $this->indexer = new DocumentIndexer(new DocumentChunker, $this->embeddings, $vector);
        $this->retriever = new ChatbotRetriever($this->embeddings, $vector, app(DocumentoPermissionService::class));

        $this->especie = DocumentoEspecie::create(['nome' => 'OFICIO', 'descricao' => 'Ofício', 'ativo' => true]);
        $gab = Gabinete::create(['nome' => 'Gabinete Geral', 'sigla' => 'GG']);
        $this->depA = Departamento::create(['nome' => 'Departamento A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $this->depB = Departamento::create(['nome' => 'Departamento B', 'sigla' => 'DB', 'gabinete_id' => $gab->id]);

        $this->userA = User::factory()->create(['departamento_id' => $this->depA->id]);
        $this->userB = User::factory()->create(['departamento_id' => $this->depB->id]);

        $this->entradaA = $this->makeEntrada($this->depA, $this->userA, 10, 'Pedido '.self::ALVO.' do setor A');
        $this->entradaB = $this->makeEntrada($this->depB, $this->userB, 11, 'Pedido '.self::ALVO.' do setor B');
        $this->internoA = $this->makeInterno($this->depA, $this->userA, 'IA', 'Nota '.self::ALVO.' A');
        $this->internoB = $this->makeInterno($this->depB, $this->userB, 'IB', 'Nota '.self::ALVO.' B');

        foreach ([$this->entradaA, $this->entradaB, $this->internoA, $this->internoB] as $doc) {
            $this->indexer->index($doc);
        }
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
            'conteudo_final' => 'Conteúdo de teste sobre '.self::ALVO,
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $owner->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => $ref,
        ]);
    }

    /** @return array<int,string> */
    private function fonteUrls(array $retrieval): array
    {
        return array_map(fn ($f) => $f['url'], $retrieval['fontes']);
    }

    public function test_indexacao_cria_chunks_com_permissao_denormalizada(): void
    {
        $this->assertGreaterThan(0, ChatbotChunk::count());
        $this->assertTrue(
            ChatbotChunk::where('departamento_id', $this->depA->id)->exists()
        );
    }

    public function test_recuperacao_so_devolve_chunks_de_documentos_permitidos(): void
    {
        $urls = $this->fonteUrls($this->retriever->retrieve($this->userA, self::ALVO));

        $this->assertContains(route('documentos-entradas.show', $this->entradaA->id), $urls);
        $this->assertContains(route('documentos-internos.show', $this->internoA->id), $urls);
        $this->assertNotContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertNotContains(route('documentos-internos.show', $this->internoB->id), $urls);
    }

    public function test_utilizador_sem_departamento_nao_recupera_nada(): void
    {
        $sem = User::factory()->create(['departamento_id' => null]);

        $r = $this->retriever->retrieve($sem, self::ALVO);

        $this->assertEmpty($r['items']);
        $this->assertEmpty($r['fontes']);
    }

    public function test_admin_recupera_de_todos_os_departamentos(): void
    {
        $legacyAdmin = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role_id' => $legacyAdmin->id, 'departamento_id' => null]);
        $admin->assignRole('admin');

        $urls = $this->fonteUrls($this->retriever->retrieve($admin, self::ALVO));

        $this->assertContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertContains(route('documentos-internos.show', $this->internoB->id), $urls);
    }

    public function test_comando_index_all_popula_o_indice(): void
    {
        ChatbotChunk::query()->delete();
        $this->assertSame(0, ChatbotChunk::count());

        $this->artisan('chatbot:index-all', ['--sync' => true])->assertSuccessful();

        $this->assertGreaterThan(0, ChatbotChunk::count());
    }

    public function test_api_requer_autenticacao(): void
    {
        $this->postJson(route('api.chatbot.perguntar'), ['pergunta' => self::ALVO])
            ->assertStatus(401);
    }

    public function test_api_pergunta_global_responde_com_fontes_permitidas(): void
    {
        $this->userA->givePermissionTo('assistente.usar');

        $resp = $this->actingAs($this->userA)
            ->postJson(route('api.chatbot.perguntar'), ['pergunta' => self::ALVO])
            ->assertStatus(200)
            ->assertJsonStructure(['conversation_id', 'resposta', 'fontes']);

        $urls = array_map(fn ($f) => $f['url'], $resp->json('fontes'));
        $this->assertNotContains(route('documentos-entradas.show', $this->entradaB->id), $urls);
        $this->assertNotContains(route('documentos-internos.show', $this->internoB->id), $urls);
    }

    public function test_api_nao_permite_perguntar_sobre_documento_de_outro_departamento(): void
    {
        $this->userA->givePermissionTo('assistente.usar');

        $this->actingAs($this->userA)
            ->postJson(route('api.chatbot.entrada', $this->entradaB), ['pergunta' => 'teor?'])
            ->assertStatus(403);
    }

    public function test_api_sem_permissao_e_proibido(): void
    {
        $this->actingAs($this->userA)
            ->postJson(route('api.chatbot.perguntar'), ['pergunta' => self::ALVO])
            ->assertStatus(403);
    }
}
