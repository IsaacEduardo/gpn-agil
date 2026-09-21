<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O painel executivo do chefe de gabinete expunha dados de outros gabinetes.
 *
 * As consultas corriam sem filtro: os contadores ("A Carecer de Despacho",
 * "Total Geral Registrado") eram de âmbito institucional e a lista de entradas
 * prioritárias trazia documentos de outros gabinetes — número, assunto e
 * procedência — cada um com botão DESPACHAR que o backend depois recusava.
 * O backend protegia a ação, mas a listagem já tinha divulgado o conteúdo.
 *
 * O administrador mantém a visão institucional; o chefe de gabinete vê o seu.
 */
class PainelChefeGabineteAmbitoTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gabA = Gabinete::create(['nome' => 'Gabinete Alfa', 'sigla' => 'GA']);
        $gabB = Gabinete::create(['nome' => 'Gabinete Beta', 'sigla' => 'GB']);

        $depA = Departamento::create(['nome' => 'Dep Alfa', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dep Beta', 'gabinete_id' => $gabB->id]);

        $chefeA = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $depA->id]);
        $gabA->update(['responsavel_id' => $chefeA->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $depA->id]);
        $admin->syncRoles(['admin']);

        $balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $depA->id]);

        $this->actingAs($balcao);
        $service = app(\App\Services\DocumentoEntradaService::class);

        $docA = $service->createDocument([
            'assunto' => 'Assunto do gabinete Alfa',
            'departamento_id' => $depA->id,
            'procedencia' => 'Procedencia Alfa',
        ]);

        $docB = $service->createDocument([
            'assunto' => 'Assunto do gabinete Beta',
            'departamento_id' => $depB->id,
            'procedencia' => 'Procedencia Beta',
        ]);

        return compact('gabA', 'gabB', 'depA', 'depB', 'chefeA', 'admin', 'balcao', 'docA', 'docB');
    }

    public function test_chefe_de_gabinete_nao_ve_entradas_de_outro_gabinete(): void
    {
        Storage::fake('public');
        ['chefeA' => $chefeA, 'docA' => $docA, 'docB' => $docB] = $this->cenario();

        $dados = app(DashboardService::class)->getChefeGabineteAdminData($chefeA->fresh());

        $ids = collect($dados['listas']['esquerda']['itens'])->pluck('id')->all();

        $this->assertContains($docA->id, $ids, 'O chefe tem de ver as entradas do seu gabinete.');
        $this->assertNotContains($docB->id, $ids, 'O painel divulgou uma entrada de outro gabinete.');
    }

    public function test_contadores_do_chefe_de_gabinete_sao_do_seu_ambito(): void
    {
        Storage::fake('public');
        ['chefeA' => $chefeA] = $this->cenario();

        $dados = app(DashboardService::class)->getChefeGabineteAdminData($chefeA->fresh());
        $kpis = collect($dados['kpis'])->keyBy('id');

        $this->assertSame(1, $kpis['carecer_despacho']['valor'], 'O contador somou documentos de outros gabinetes.');
        $this->assertSame(1, $kpis['total_registrado_ano']['valor'], 'O total do ano somou documentos de outros gabinetes.');
    }

    /**
     * O administrador governa a instituição inteira: o seu painel continua a
     * ser institucional.
     */
    public function test_administrador_mantem_visao_institucional(): void
    {
        Storage::fake('public');
        ['admin' => $admin, 'docA' => $docA, 'docB' => $docB] = $this->cenario();

        $dados = app(DashboardService::class)->getChefeGabineteAdminData($admin->fresh());

        $ids = collect($dados['listas']['esquerda']['itens'])->pluck('id')->all();
        $kpis = collect($dados['kpis'])->keyBy('id');

        $this->assertContains($docA->id, $ids);
        $this->assertContains($docB->id, $ids);
        $this->assertSame(2, $kpis['total_registrado_ano']['valor']);
    }
}
