<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoEntradaWorkflowViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    public function test_fluxo_completo_visivel_na_view_com_stepper_timeline_recebedor_e_auditoria(): void
    {
        $roleAdmin = Role::where('name', 'admin')->firstOrFail();
        $roleChefe = Role::where('name', 'chefe-departamento')->firstOrFail();

        $gab = Gabinete::create(['nome' => 'Gabinete Central', 'sigla' => 'GC']);
        $depA = Departamento::create(['nome' => 'Departamento de Operações', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Departamento Financeiro', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $depA->id]);
        $admin->assignRole($roleAdmin);

        $chefe = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $depA->id]);
        $chefe->assignRole($roleChefe);
        $depA->update(['responsavel_id' => $chefe->id]);

        $recebedor = User::factory()->create(['name' => 'Manuel Recebedor', 'departamento_id' => $depB->id]);

        // 1. Criar Documento com Vistos e Despacho
        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 99,
            'ano_referencia' => 2026,
            'data_entrada' => now()->subDays(3),
            'classificacao_especie' => 'Ofício',
            'classificacao_ref_numero' => 'OF-999/2026',
            'procedencia' => 'Ministério da Justiça',
            'assunto' => 'Processo de Auditoria e Modernização',
            'departamento_id' => $depA->id,
            'user_id' => $admin->id,
            'status' => 'tratado',
            'visto_departamento_status' => 'aprovado',
            'visto_departamento_por' => $chefe->id,
            'visto_departamento_data' => now()->subDays(2),
            'visto_departamento_observacao' => 'Validado favoravelmente.',
            'texto_despacho' => 'Autorizo o prosseguimento imediato para o departamento financeiro.',
            'data_despacho' => now()->subDay(),
            'despachado_por_id' => $admin->id,
        ]);

        // 2. Criar Encaminhamento Interno com Recebimento Confirmado
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $admin->id,
            'encaminhado_em' => now()->subHours(10),
            'recebido_em' => now()->subHours(8),
            'recebido_por_id' => $recebedor->id,
            'observacao' => 'Favor analisar custos.',
            'status' => 'recebido',
        ]);

        // 3. Criar Encaminhamento Externo
        DocumentoEncaminhamentoExterno::create([
            'documento_entrada_id' => $doc->id,
            'origem_gabinete_id' => $gab->id,
            'destino_gabinete_id' => $gab->id,
            'usuario_id' => $admin->id,
            'oficio_numero' => 'EXT-555/2026',
            'enviado_em' => now()->subHours(5),
            'observacao' => 'Cópia para o arquivo do gabinete.',
            'status' => 'enviado',
        ]);

        // 4. Criar Tarefa
        DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'titulo' => 'Emitir Parecer Financeiro',
            'descricao' => 'Conferir tabela orçamentária.',
            'assigned_by_id' => $chefe->id,
            'assigned_to_user_id' => $recebedor->id,
            'status' => 'concluida',
            'responsavel_id' => $recebedor->id,
        ]);

        // 5. Criar AuditLog
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'auditable_type' => DocumentoEntrada::class,
            'auditable_id' => $doc->id,
            'old_values' => ['status' => 'registrado'],
            'new_values' => ['status' => 'tratado'],
            'ip_address' => '192.168.1.100',
        ]);

        // Acessar a página de detalhes
        $response = $this->actingAs($admin)->get(route('documentos-entradas.show', $doc));

        $response->assertStatus(200);

        // 1. Validar Stepper
        $response->assertSee('Fluxo do Documento (Pipeline de Tramitação)', false);
        $response->assertSee('1. Registo & Protocolo');
        $response->assertSee('2. Validação & Visto');
        $response->assertSee('3. Despacho / Diretriz', false);
        $response->assertSee('4. Tramitação & Tarefas');
        $response->assertSee('5. Resposta & Arquivo');

        // 2. Validar Linha do Tempo Unificada
        $response->assertSee('Linha do Tempo Cronológica Unificada', false);
        $response->assertSee('Registo e Entrada no Sistema', false);
        $response->assertSee('Validado favoravelmente', false);
        $response->assertSee('Despacho Emitido pelo Gabinete', false);
        $response->assertSee('Autorizo o prosseguimento imediato', false);
        $response->assertSee('Recebimento Confirmado: Departamento Financeiro', false);
        $response->assertSee('Saída Externa', false);
        $response->assertSee('EXT-555/2026', false);
        $response->assertSee('Tarefa Concluída: Emitir Parecer Financeiro', false);

        // 3. Validar Exibição Explícita de Recebedor no Histórico Interno
        $response->assertSee('Manuel Recebedor', false);
        $response->assertSee('Recebido no Setor', false);

        // 4. Validar Aba de Auditoria
        $response->assertSee('Trilha de Auditoria & Modificações', false);
        $response->assertSee('192.168.1.100', false);
        $response->assertSee('registrado', false);
        $response->assertSee('tratado', false);
    }

    public function test_botao_despachar_visivel_no_show_para_utilizador_autorizado(): void
    {
        $roleAdmin = Role::where('name', 'admin')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete Superior']);
        $dep = Departamento::create(['nome' => 'Departamento Geral', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $dep->id]);
        $admin->assignRole($roleAdmin);

        // Documento pendente de tratamento
        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 100,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'assunto' => 'Documento Pendente de Despacho',
            'departamento_id' => $dep->id,
            'user_id' => $admin->id,
            'status' => 'pendente_tratamento',
        ]);

        $response = $this->actingAs($admin)->get(route('documentos-entradas.show', $doc));

        $response->assertStatus(200);
        // O botão Despachar deve estar visível agora no show
        $response->assertSee('Despachar', false);
        $response->assertSee('modalDespacho' . $doc->id, false);
    }
}
