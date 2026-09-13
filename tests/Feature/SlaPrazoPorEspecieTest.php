<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6 — o SLA usava limiares fixos de 2 e 5 dias para todo o tipo de
 * documento. Um ofício urgente e um relatório anual tinham o mesmo prazo.
 */
class SlaPrazoPorEspecieTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $dep;

    private User $ator;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->ator = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $this->dep->id]);
    }

    private function especie(string $nome, ?int $prazo): DocumentoEspecie
    {
        return DocumentoEspecie::updateOrCreate(
            ['nome' => $nome],
            ['ativo' => true, 'ordem' => 1, 'prazo_tratamento_dias' => $prazo],
        );
    }

    private function doc(string $especie, int $diasAtras, int $seq = 1): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays($diasAtras),
            'classificacao_especie' => $especie,
            'assunto' => 'Teste de prazo',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->ator->id,
            'status' => 'registrado',
        ]);
    }

    public function test_especie_com_prazo_longo_nao_fica_critica_cedo(): void
    {
        $this->especie('Relatório Anual', 30);

        // Com o limiar fixo de 5 dias isto seria 'critical'.
        $this->assertSame('normal', $this->doc('Relatório Anual', 6, 1)->sla_status);
        $this->assertSame('warning', $this->doc('Relatório Anual', 12, 2)->sla_status);
        $this->assertSame('critical', $this->doc('Relatório Anual', 31, 3)->sla_status);
    }

    public function test_especie_urgente_fica_critica_mais_cedo(): void
    {
        $this->especie('Ofício Urgente', 2);

        // Com o limiar fixo isto seria apenas 'warning'.
        $this->assertSame('critical', $this->doc('Ofício Urgente', 2, 4)->sla_status);
        $this->assertSame('warning', $this->doc('Ofício Urgente', 1, 5)->sla_status);
        $this->assertSame('normal', $this->doc('Ofício Urgente', 0, 6)->sla_status);
    }

    /** Sem prazo definido, o comportamento tem de ser exatamente o de hoje. */
    public function test_especie_sem_prazo_mantem_o_comportamento_anterior(): void
    {
        $this->especie('Carta', null);

        $this->assertSame('normal', $this->doc('Carta', 1, 7)->sla_status);
        $this->assertSame('warning', $this->doc('Carta', 2, 8)->sla_status);
        $this->assertSame('critical', $this->doc('Carta', 5, 9)->sla_status);
    }

    public function test_especie_desconhecida_mantem_o_comportamento_anterior(): void
    {
        $this->assertSame('warning', $this->doc('Espécie Que Não Existe', 3, 10)->sla_status);
        $this->assertSame('critical', $this->doc('Espécie Que Não Existe', 5, 11)->sla_status);
    }

    public function test_documento_sem_especie_mantem_o_comportamento_anterior(): void
    {
        $doc = $this->doc('Carta', 5, 12);
        $doc->classificacao_especie = null;
        $doc->save();

        $this->assertSame('critical', $doc->fresh()->sla_status);
    }

    /**
     * sla_status é lido por linha na listagem: o prazo tem de vir de um mapa em
     * cache, não de uma query por documento.
     */
    public function test_nao_faz_query_por_documento(): void
    {
        $this->especie('Ofício', 7);

        $docs = collect(range(20, 29))->map(fn ($i) => $this->doc('Ofício', 3, $i));

        // Primeira leitura aquece a cache.
        $docs->first()->sla_status;

        DB::enableQueryLog();
        DB::flushQueryLog();

        foreach ($docs as $doc) {
            $doc->sla_status;
        }

        $this->assertCount(0, DB::getQueryLog(), 'sla_status não pode consultar a base por documento.');
        DB::disableQueryLog();
    }

    public function test_estados_terminais_continuam_sem_sla(): void
    {
        $this->especie('Ofício', 1);

        $doc = $this->doc('Ofício', 90, 30);
        $doc->status = 'arquivado';
        $doc->save();

        $this->assertSame('normal', $doc->fresh()->sla_status);
    }
}
