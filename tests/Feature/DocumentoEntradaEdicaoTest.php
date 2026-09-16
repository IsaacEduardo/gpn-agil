<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Correção do registo de um documento de entrada.
 *
 * O caso de uso é a gralha de digitação apanhada logo a seguir ao registo. O que
 * se exige: que a correção chegue mesmo a gravar, que alcance todos os campos do
 * registo — incluindo a data de entrada, que conta para o prazo — e que não sirva
 * de atalho para atos de tramitação que têm regra própria.
 */
class DocumentoEntradaEdicaoTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->dep->id]);
        $this->autor->assignRole($roleUser);

        DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true, 'ordem' => 1]);
    }

    private function documento(array $overrides = []): DocumentoEntrada
    {
        return DocumentoEntrada::create(array_merge([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDay(),
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->autor->id,
            'status' => 'registrado',
        ], $overrides));
    }

    private function payload(DocumentoEntrada $doc, array $overrides = []): array
    {
        return array_merge([
            'classificacao_especie' => $doc->classificacao_especie,
            'assunto' => $doc->assunto,
        ], $overrides);
    }

    /**
     * O campo existia no formulário mas nem era validado nem sincronizado: o
     * utilizador corrigia a palavra-chave, recebia "atualizado com sucesso" e
     * nada mudava.
     */
    public function test_tags_sao_gravadas_na_edicao(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'tags' => 'urgente, financeiro',
            ]))
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['urgente', 'financeiro'],
            $doc->fresh()->tags->pluck('nome')->all()
        );
    }

    /** Corrigir uma tag mal escrita passa por conseguir tirá-la. */
    public function test_tags_podem_ser_limpas_na_edicao(): void
    {
        $doc = $this->documento();
        $tag = Tag::create(['nome' => 'urgemte', 'slug' => 'urgemte']);
        $doc->tags()->sync([$tag->id]);

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, ['tags' => '']))
            ->assertRedirect();

        $this->assertCount(0, $doc->fresh()->tags);
    }

    /**
     * A data de entrada alimenta o SLA e era o único campo do registo sem
     * qualquer via de correção — não estava no formulário nem na validação.
     */
    public function test_data_de_entrada_e_corrigivel(): void
    {
        $doc = $this->documento(['data_entrada' => now()->subDays(10)]);
        $correta = now()->subDays(2)->startOfDay();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'data_entrada' => $correta->toDateString(),
            ]))
            ->assertRedirect();

        $this->assertSame($correta->toDateString(), $doc->fresh()->data_entrada->toDateString());
    }

    /** Mesma regra do registo: uma entrada no futuro contaminaria o prazo. */
    public function test_data_de_entrada_no_futuro_e_recusada(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'data_entrada' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('data_entrada');
    }

    /**
     * A saída de gabinete tem policy própria (só o responsável do gabinete),
     * cria o encaminhamento externo, muda o status e notifica o destino. Pelo
     * formulário de edição não acontecia nada disso — e a saída verdadeira ficava
     * depois barrada por "documento já possui saída registada".
     */
    public function test_edicao_nao_regista_saida_de_gabinete(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'saida_gabinete_data' => now()->toDateString(),
                'encaminhamento_orgao' => 'Gabinete Inventado',
                'encaminhamento_oficio_numero' => 'OF/999',
            ]))
            ->assertRedirect();

        $doc = $doc->fresh();

        $this->assertNull($doc->saida_gabinete_data);
        $this->assertNull($doc->encaminhamento_orgao);
        $this->assertNull($doc->encaminhamento_oficio_numero);
        $this->assertDatabaseCount('documento_encaminhamentos_externos', 0);
    }

    /** A correção normal do registo continua a funcionar. */
    public function test_assunto_e_corrigido(): void
    {
        $doc = $this->documento(['assunto' => 'Pedido de pareser']);

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Pedido de parecer',
            ]))
            ->assertRedirect();

        $this->assertSame('Pedido de parecer', $doc->fresh()->assunto);
    }
}
