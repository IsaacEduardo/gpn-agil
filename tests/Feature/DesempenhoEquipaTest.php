<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DesempenhoEquipaService;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Relatórios › Desempenho da equipa (2026-10-02): indicadores por técnico e
 * âmbito decidido no servidor.
 */
class DesempenhoEquipaTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $depA;

    private Departamento $depB;

    private User $chefeGab;

    private User $chefeB;

    private User $admin;

    private User $tecA;

    private User $tecB1;

    private User $tecB2;

    private DocumentoEntrada $doc;

    /** Período fixo dos testes: setembro de 2026. */
    private array $setembro = ['granularity' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gab = Gabinete::create(['nome' => 'Gabinete Desempenho', 'sigla' => 'GDES']);
        $this->depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gab->id]);

        $this->chefeGab = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);
        $gab->update(['responsavel_id' => $this->chefeGab->id]);

        $this->chefeB = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $this->depB->id]);
        $this->chefeB->syncRoles(['chefe-departamento']);
        $this->depB->update(['responsavel_id' => $this->chefeB->id]);

        $outroGab = Gabinete::create(['nome' => 'Gabinete Informática', 'sigla' => 'GINF']);
        $depInf = Departamento::create(['nome' => 'Informática', 'gabinete_id' => $outroGab->id]);
        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $depInf->id]);
        $this->admin->syncRoles(['admin']);

        $this->tecA = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id, 'name' => 'Técnico Alfa']);
        $this->tecB1 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id, 'name' => 'Técnico Beta Um']);
        $this->tecB2 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id, 'name' => 'Técnico Beta Dois']);

        $this->actingAs($this->chefeGab);
        $this->doc = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->depB->id,
        ])->fresh();
    }

    /** Tarefa com datas controladas; created_at é a atribuição. */
    private function tarefa(User $tecnico, string $criada, array $campos = []): DocumentoTarefa
    {
        $tarefa = new DocumentoTarefa(array_merge([
            'documento_entrada_id' => $this->doc->id,
            'titulo' => 'Parecer',
            'assigned_by_id' => $this->chefeB->id,
            'assigned_to_user_id' => $tecnico->id,
            'status' => 'pendente',
        ], $campos));
        $tarefa->created_at = Carbon::parse($criada);
        $tarefa->updated_at = Carbon::parse($criada);
        $tarefa->save();

        return $tarefa;
    }

    private function linhaDe(array $dados, User $tecnico): array
    {
        return $dados['linhas']->firstWhere('user_id', $tecnico->id);
    }

    // --- Indicadores -----------------------------------------------------------------

    public function test_indicadores_do_tecnico(): void
    {
        $t = $this->tecB1;
        // No prazo, concluída no próprio dia do prazo (prazo é uma data).
        $this->tarefa($t, '2026-09-02 09:00', ['status' => 'concluida', 'prazo_at' => '2026-09-04', 'concluida_em' => '2026-09-04 16:00']);
        // Fora do prazo.
        $this->tarefa($t, '2026-09-03 09:00', ['status' => 'concluida', 'prazo_at' => '2026-09-05', 'concluida_em' => '2026-09-07 10:00']);
        // Concluída sem prazo: conta na conclusão, não no cumprimento.
        $this->tarefa($t, '2026-09-05 09:00', ['status' => 'concluida', 'concluida_em' => '2026-09-05 15:00']);
        // Concluída antes de existir concluida_em: fora das medianas e do cumprimento.
        $this->tarefa($t, '2026-09-06 09:00', ['status' => 'concluida', 'prazo_at' => '2026-09-10']);
        // Pendente fora do prazo (hoje é 20/09) e pendente dentro dele.
        $this->tarefa($t, '2026-09-08 09:00', ['prazo_at' => '2026-09-15']);
        $this->tarefa($t, '2026-09-09 09:00', ['prazo_at' => '2026-09-25']);
        // Não contam: retirada, cancelada, e atribuída fora do período.
        $this->tarefa($t, '2026-09-10 09:00', ['status' => DocumentoTarefa::STATUS_RETIRADA]);
        $this->tarefa($t, '2026-09-10 09:00', ['status' => 'cancelada']);
        $this->tarefa($t, '2026-08-30 09:00', ['status' => 'concluida', 'concluida_em' => '2026-08-31 09:00']);

        $l = $this->linhaDe(app(DesempenhoEquipaService::class)->dados($this->setembro, $this->chefeB), $t);

        $this->assertSame(6, $l['recebidas']);
        $this->assertSame(4, $l['concluidas']);
        $this->assertSame(2, $l['pendentes']);
        $this->assertSame(1, $l['pendentes_atrasadas']);
        $this->assertEqualsWithDelta(66.7, $l['taxa_conclusao'], 0.05);
        $this->assertSame(2, $l['avaliadas_prazo']);
        $this->assertSame(1, $l['no_prazo']);
        $this->assertEqualsWithDelta(50.0, $l['cumprimento_prazo'], 0.05);
        $this->assertSame(1, $l['sem_data_conclusao']);
        // Resoluções: 55 h, 97 h, 6 h -> mediana 55 h.
        $this->assertSame(3, $l['n_resolucao']);
        $this->assertEqualsWithDelta(55.0, $l['mediana_resolucao_horas'], 0.05);
    }

    public function test_concorrencia_mede_desde_que_foi_assumida(): void
    {
        $this->tarefa($this->tecB1, '2026-09-02 08:00', [
            'status' => 'concluida',
            'grupo_tarefa_uuid' => 'g-1',
            'modo_grupo' => DocumentoTarefa::MODO_CONCORRENCIA,
            'assumida_em' => '2026-09-02 10:00',
            'concluida_em' => '2026-09-02 13:00',
            'prazo_at' => '2026-09-03',
        ]);
        // O colega a quem foi retirada não conta.
        $this->tarefa($this->tecB2, '2026-09-02 08:00', [
            'status' => DocumentoTarefa::STATUS_RETIRADA,
            'grupo_tarefa_uuid' => 'g-1',
            'modo_grupo' => DocumentoTarefa::MODO_CONCORRENCIA,
        ]);
        // Assumida e parada além do prazo.
        $this->tarefa($this->tecB1, '2026-09-05 08:00', [
            'grupo_tarefa_uuid' => 'g-2',
            'modo_grupo' => DocumentoTarefa::MODO_CONCORRENCIA,
            'assumida_em' => '2026-09-05 09:00',
            'prazo_at' => '2026-09-10',
        ]);

        $dados = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->chefeB);
        $l = $this->linhaDe($dados, $this->tecB1);

        $this->assertSame(2, $l['assumidas']);
        $this->assertSame(1, $l['assumidas_em_atraso']);
        $this->assertEqualsWithDelta(3.0, $l['mediana_resolucao_horas'], 0.05, 'da assunção à conclusão');
        $this->assertEqualsWithDelta(1.5, $l['mediana_ate_assumir_horas'], 0.05, 'mediana de 2 h e 1 h');
        $this->assertSame(0, $this->linhaDe($dados, $this->tecB2)['recebidas']);
    }

    public function test_pagina_mostra_os_numeros(): void
    {
        $this->tarefa($this->tecB1, '2026-09-02 09:00', ['status' => 'concluida', 'prazo_at' => '2026-09-04', 'concluida_em' => '2026-09-03 09:00']);

        $this->actingAs($this->chefeB)->get(route('relatorios.desempenho', $this->setembro))
            ->assertOk()
            ->assertSee('Desempenho da equipa')
            ->assertSee('Técnico Beta Um')
            ->assertSee('Técnico Beta Dois')
            ->assertSee('100%');
    }

    // --- Âmbito ----------------------------------------------------------------------

    public function test_chefe_de_departamento_ve_so_os_seus(): void
    {
        $nomes = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->chefeB)['linhas']->pluck('nome');

        $this->assertContains('Técnico Beta Um', $nomes);
        $this->assertContains('Técnico Beta Dois', $nomes);
        $this->assertNotContains('Técnico Alfa', $nomes);

        $this->actingAs($this->chefeB)
            ->get(route('relatorios.desempenho', $this->setembro + ['departamento_id' => $this->depA->id]))
            ->assertForbidden();
    }

    public function test_chefe_de_gabinete_ve_o_gabinete(): void
    {
        $nomes = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->chefeGab)['linhas']->pluck('nome');

        $this->assertContains('Técnico Alfa', $nomes);
        $this->assertContains('Técnico Beta Um', $nomes);
    }

    public function test_tecnico_ve_so_os_seus_numeros(): void
    {
        $this->tarefa($this->tecB2, '2026-09-02 09:00', ['status' => 'concluida', 'concluida_em' => '2026-09-02 12:00']);

        $dados = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->tecB1);
        $this->assertSame('proprio', $dados['escopo']['tipo']);
        $this->assertSame([$this->tecB1->id], $dados['linhas']->pluck('user_id')->all());

        $this->actingAs($this->tecB1)->get(route('relatorios.desempenho', $this->setembro))
            ->assertOk()
            ->assertSee('O meu desempenho')
            ->assertDontSee('Técnico Beta Dois');
    }

    public function test_tecnico_que_pede_um_departamento_e_recusado(): void
    {
        $this->actingAs($this->tecB1)
            ->get(route('relatorios.desempenho', $this->setembro + ['departamento_id' => $this->depB->id]))
            ->assertForbidden();
    }

    public function test_permissao_sem_chefia_da_o_proprio_departamento(): void
    {
        Permission::findOrCreate('relatorios.desempenho', 'web');
        $this->tecB1->givePermissionTo('relatorios.desempenho');

        $nomes = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->tecB1->fresh())['linhas']->pluck('nome');

        $this->assertContains('Técnico Beta Dois', $nomes);
        $this->assertNotContains('Técnico Alfa', $nomes);
    }

    public function test_admin_ve_todos_os_que_tiveram_tarefas(): void
    {
        $this->tarefa($this->tecA, '2026-09-02 09:00');
        $this->tarefa($this->tecB1, '2026-09-02 09:00');

        $nomes = app(DesempenhoEquipaService::class)->dados($this->setembro, $this->admin)['linhas']->pluck('nome');

        $this->assertEqualsCanonicalizing(['Técnico Alfa', 'Técnico Beta Um'], $nomes->all());
    }

    public function test_menu_leva_o_tecnico_ao_seu_desempenho(): void
    {
        // Sem acesso aos Relatórios, o técnico tem o atalho no menu principal.
        $this->actingAs($this->tecB1)->get(route('documentos-entradas.index'))
            ->assertOk()
            ->assertSee('href="'.route('relatorios.desempenho').'"', false)
            ->assertDontSee('href="'.route('relatorios.index').'"', false);

        // A chefia continua a ver "Relatórios" (e a aba lá dentro).
        $this->actingAs($this->chefeB)->get(route('documentos-entradas.index'))
            ->assertOk()
            ->assertSee('href="'.route('relatorios.index').'"', false);
    }

    public function test_visitante_e_enviado_para_o_login(): void
    {
        auth()->logout();

        $this->get(route('relatorios.desempenho'))->assertRedirect(route('login'));
    }
}
