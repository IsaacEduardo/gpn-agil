<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Delegar uma tarefa não pode reescrever o despacho do gabinete.
 *
 * A criação de tarefa gravava despachado_por_id, data_despacho e o visto do
 * GABINETE com o autor e a hora da delegação. Numa delegação posterior ao
 * despacho — o caso corrente — a ficha passava a atestar que o despacho do
 * gabinete fora emitido pelo chefe de departamento, à hora em que este delegou.
 * O registo administrativo passava a afirmar um facto falso e um chefe de
 * departamento constava como tendo dado o visto do Gabinete, quebrando a
 * segregação de funções.
 *
 * O ato do gabinete pertence a quem o praticou. A delegação regista o visto do
 * DEPARTAMENTO, que é o que a regra de negócio sempre disse ser.
 */
class DelegacaoNaoReescreveDespachoTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);

        $gab = Gabinete::create(['nome' => 'Gabinete Fluxo', 'sigla' => 'GFLX']);
        $dep = Departamento::create(['nome' => 'Departamento Alfa', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $dep->id]);
        $chefe = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $dep->id]);
        $tecnico = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);

        $dep->update(['chefe_user_id' => $chefe->id]);
        $gab->update(['responsavel_id' => $admin->id]);

        return compact('gab', 'dep', 'admin', 'chefe', 'tecnico');
    }

    public function test_delegacao_preserva_autoria_e_data_do_despacho_do_gabinete(): void
    {
        Storage::fake('public');

        ['dep' => $dep, 'admin' => $admin, 'chefe' => $chefe, 'tecnico' => $tecnico] = $this->cenario();

        $service = app(DocumentoEntradaService::class);

        $this->actingAs($admin);
        $doc = $service->createDocument([
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $dep->id,
        ]);

        // O gabinete despacha, pela mão do administrador.
        $service->despacharDocumento($doc, 'Ao departamento, para parecer.', [$dep->id], $admin);
        $doc->refresh();

        $despachadoPor = $doc->despachado_por_id;
        $dataDespacho = $doc->data_despacho;
        $vistoGabPor = $doc->visto_gabinete_por;
        $vistoGabData = $doc->visto_gabinete_data;
        $textoDespacho = $doc->texto_despacho;

        $this->assertSame($admin->id, $despachadoPor, 'Pré-condição: o despacho é do administrador.');

        // Mais tarde, a chefia do departamento delega uma tarefa ao técnico.
        $this->travel(11)->minutes();
        $service->createTask($doc, [
            'titulo' => 'Elaborar parecer técnico',
            'assigned_to_user_id' => $tecnico->id,
        ], $chefe);

        $doc->refresh();

        $this->assertSame($despachadoPor, $doc->despachado_por_id, 'A delegação reescreveu quem despachou.');
        $this->assertEquals($dataDespacho, $doc->data_despacho, 'A delegação reescreveu a data do despacho.');
        $this->assertSame($vistoGabPor, $doc->visto_gabinete_por, 'A delegação reescreveu o autor do visto do gabinete.');
        $this->assertEquals($vistoGabData, $doc->visto_gabinete_data, 'A delegação reescreveu a data do visto do gabinete.');
        $this->assertSame($textoDespacho, $doc->texto_despacho, 'A delegação reescreveu o texto do despacho.');

        // E regista, essa sim, a aprovação do departamento.
        $this->assertSame($chefe->id, $doc->visto_departamento_por);
        $this->assertSame('aprovado', $doc->visto_departamento_status);
        $this->assertNotNull($doc->visto_departamento_data);
    }

    /**
     * Sem despacho prévio, a delegação continua a não inventar um: o documento
     * fica com o visto do departamento e nada mais.
     */
    public function test_delegacao_sem_despacho_previo_nao_fabrica_despacho(): void
    {
        Storage::fake('public');

        ['dep' => $dep, 'admin' => $admin, 'chefe' => $chefe, 'tecnico' => $tecnico] = $this->cenario();

        $service = app(DocumentoEntradaService::class);

        $this->actingAs($admin);
        $doc = $service->createDocument([
            'assunto' => 'Documento sem despacho',
            'departamento_id' => $dep->id,
        ]);

        $service->createTask($doc, [
            'titulo' => 'Tratar assunto',
            'assigned_to_user_id' => $tecnico->id,
        ], $chefe);

        $doc->refresh();

        $this->assertNull($doc->despachado_por_id);
        $this->assertNull($doc->data_despacho);
        $this->assertNull($doc->visto_gabinete_por);
        $this->assertNull($doc->visto_gabinete_data);
        $this->assertEmpty($doc->texto_despacho);

        $this->assertSame($chefe->id, $doc->visto_departamento_por);
    }

    /**
     * Uma segunda tarefa não redata o visto departamental já dado: o primeiro
     * é que conta.
     */
    public function test_segunda_tarefa_nao_reescreve_o_visto_do_departamento(): void
    {
        Storage::fake('public');

        ['dep' => $dep, 'admin' => $admin, 'chefe' => $chefe, 'tecnico' => $tecnico] = $this->cenario();

        $service = app(DocumentoEntradaService::class);

        $this->actingAs($admin);
        $doc = $service->createDocument([
            'assunto' => 'Documento com duas tarefas',
            'departamento_id' => $dep->id,
        ]);

        $service->createTask($doc, ['titulo' => 'Primeira', 'assigned_to_user_id' => $tecnico->id], $chefe);
        $doc->refresh();
        $primeiroVisto = $doc->visto_departamento_data;

        $this->travel(7)->minutes();
        $service->createTask($doc, ['titulo' => 'Segunda', 'assigned_to_user_id' => $tecnico->id], $chefe);
        $doc->refresh();

        $this->assertEquals($primeiroVisto, $doc->visto_departamento_data);
    }
}
