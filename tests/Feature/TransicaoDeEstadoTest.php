<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\Role;
use App\Models\User;
use App\Services\ArchiveService;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * O estado do documento muda num sítio só, e só por caminhos legítimos.
 *
 * O `status` era atribuído à mão em oito sítios espalhados por três classes,
 * sem ninguém verificar nada. Sem ponto único não havia onde recusar um salto
 * ilegal — e o desarquivamento repunha 'registrado' fosse qual fosse o percurso
 * já feito, fazendo um documento tratado reaparecer na fila de quem espera
 * despacho.
 *
 * @see \App\Services\DocumentoEntradaService::transitionTo()
 * @see \App\Enums\DocumentoStatus::transicoesDeDocumentoEntrada()
 */
class TransicaoDeEstadoTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gab = Gabinete::create(['nome' => 'Gabinete Fluxo', 'sigla' => 'GFLX']);
        $dep = Departamento::create(['nome' => 'Departamento Alfa', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $dep->id]);
        $admin->syncRoles(['admin']);
        $gab->update(['responsavel_id' => $admin->id]);

        return compact('gab', 'dep', 'admin');
    }

    private function documento(array $c, string $status): DocumentoEntrada
    {
        $this->actingAs($c['admin']);

        $doc = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Documento de teste',
            'departamento_id' => $c['dep']->id,
        ]);

        $doc->status = $status;
        $doc->save();

        return $doc->fresh();
    }

    public static function transicoesLegitimas(): array
    {
        return [
            'despacho'            => ['pendente_tratamento', 'encaminhado'],
            'recebimento'         => ['encaminhado', 'recebido'],
            'fecho do setor'      => ['recebido', 'tratado'],
            'reencaminhar'        => ['tratado', 'encaminhado'],
            'reabrir por tarefa'  => ['tratado', 'recebido'],
            'saída de gabinete'   => ['recebido', 'encaminhado_externo'],
            'arquivar'            => ['tratado', 'arquivado'],
            'desarquivar'         => ['arquivado', 'tratado'],
        ];
    }

    #[DataProvider('transicoesLegitimas')]
    public function test_transicao_legitima_e_aceite(string $de, string $para): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documento($c, $de);

        $mudou = app(DocumentoEntradaService::class)
            ->transitionTo($doc, DocumentoStatus::from($para), 'teste');

        $this->assertTrue($mudou);
        $this->assertSame($para, $doc->fresh()->status);
    }

    /**
     * Arquivado só sai por desarquivamento, que repõe um estado do percurso.
     * Nunca salta diretamente para um estado de trabalho sem passar por lá.
     */
    public function test_transicao_ilegitima_e_recusada(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documento($c, 'arquivado');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/arquivado → finalizado/');

        app(DocumentoEntradaService::class)
            ->transitionTo($doc, DocumentoStatus::FINALIZADO, 'salto ilegítimo');
    }

    public function test_mesmo_estado_nao_e_erro_e_grava_os_outros_campos(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documento($c, 'recebido');

        $doc->observacoes = 'Anotação do balcão.';

        $mudou = app(DocumentoEntradaService::class)
            ->transitionTo($doc, DocumentoStatus::RECEBIDO, 'idempotente');

        $this->assertFalse($mudou, 'Repetir o mesmo estado não é uma transição.');
        $this->assertSame('Anotação do balcão.', $doc->fresh()->observacoes);
    }

    /**
     * O desarquivamento repunha sempre 'registrado' — o valor legado do estado
     * de nascença — apagando o percurso já feito.
     */
    public function test_desarquivar_repoe_o_estado_anterior_ao_arquivo(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documento($c, 'tratado');

        $pasta = Pasta::create([
            'nome' => 'Pasta 2026',
            'departamento_id' => $c['dep']->id,
            'gabinete_id' => $c['gab']->id,
            'created_by' => $c['admin']->id,
            'type' => 'entrada',
        ]);

        app(ArchiveService::class)->archive($doc, $c['admin'], $pasta->id);
        $doc->refresh();

        $this->assertTrue((bool) $doc->arquivado);
        $this->assertSame('arquivado', $doc->status);
        $this->assertSame('tratado', $doc->status_pre_arquivo, 'O estado anterior tem de ficar guardado.');

        $this->actingAs($c['admin'])
            ->post(route('documentos-entradas.desarquivar', $doc->id))
            ->assertRedirect();

        $doc->refresh();

        $this->assertFalse((bool) $doc->arquivado);
        $this->assertSame('tratado', $doc->status, 'Desarquivar atirou o documento para o início do percurso.');
        $this->assertNull($doc->status_pre_arquivo);
    }

    /**
     * A hora do encaminhamento faz parte do registo: dois encaminhamentos do
     * mesmo dia têm de ser distinguíveis.
     */
    public function test_encaminhamento_data_guarda_a_hora(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $this->actingAs($c['admin']);

        $doc = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Documento com hora',
            'departamento_id' => $c['dep']->id,
        ]);

        $this->travelTo(now()->setTime(14, 37, 5));

        app(DocumentoEntradaService::class)
            ->despacharDocumento($doc, 'Ao departamento.', [$c['dep']->id], $c['admin']);

        $gravada = $doc->fresh()->encaminhamento_data;

        $this->assertNotNull($gravada);
        $this->assertSame('14:37', $gravada->format('H:i'), 'A hora do encaminhamento perdeu-se.');
    }
}
