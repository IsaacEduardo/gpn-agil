<?php

namespace Tests\Feature;

use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Responsável da instituição (ex.: o Governador) nos dados da instituição e nos
 * marcadores {{INSTITUICAO_RESPONSAVEL_NOME}} / {{INSTITUICAO_RESPONSAVEL_CARGO}}
 * dos modelos de documento (2026-10-02).
 */
class InstituicaoResponsavelTest extends TestCase
{
    use RefreshDatabase;

    private Role $roleAdmin;

    private Role $roleUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $this->roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
    }

    private function dadosBase(array $extra = []): array
    {
        return array_merge([
            'nome_oficial' => 'Governo Provincial da Huíla',
            'sigla' => 'GPH',
            'cidade' => 'Lubango',
        ], $extra);
    }

    private function utilizadorDeGabinete(): User
    {
        $responsavelGabinete = User::factory()->create(['name' => 'Secretária Geral', 'role_id' => $this->roleUser->id]);
        $gab = Gabinete::create(['nome' => 'Secretaria Geral', 'responsavel_id' => $responsavelGabinete->id]);
        $dep = Departamento::create(['nome' => 'Departamento de Teste', 'gabinete_id' => $gab->id]);

        return User::factory()->create(['role_id' => $this->roleUser->id, 'departamento_id' => $dep->id]);
    }

    private function processar(string $template, User $user): string
    {
        return app(DocumentoInternoService::class)->processarTemplate($template, null, $user);
    }

    public function test_admin_grava_nome_e_cargo_do_responsavel(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleAdmin->id]);

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $this->dadosBase([
                'responsavel_nome' => 'Nome do Governador',
                'responsavel_cargo' => 'Governador Provincial da Huíla',
            ]))
            ->assertRedirect(route('admin.instituicao.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dados_instituicao', [
            'responsavel_nome' => 'Nome do Governador',
            'responsavel_cargo' => 'Governador Provincial da Huíla',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.instituicao.edit'))
            ->assertOk()
            ->assertSee('value="Nome do Governador"', false);
    }

    public function test_quem_nao_e_admin_nao_altera_o_responsavel(): void
    {
        $user = User::factory()->create(['role_id' => $this->roleUser->id]);

        $this->actingAs($user)
            ->put(route('admin.instituicao.update'), $this->dadosBase(['responsavel_nome' => 'Intruso']))
            ->assertForbidden();

        $this->assertDatabaseMissing('dados_instituicao', ['responsavel_nome' => 'Intruso']);
    }

    public function test_validacao_recusa_mais_de_150_caracteres(): void
    {
        $admin = User::factory()->create(['role_id' => $this->roleAdmin->id]);

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $this->dadosBase([
                'responsavel_nome' => str_repeat('a', 151),
                'responsavel_cargo' => str_repeat('b', 151),
            ]))
            ->assertSessionHasErrors(['responsavel_nome', 'responsavel_cargo']);
    }

    public function test_marcadores_resolvem_com_e_sem_espacos(): void
    {
        DadosInstituicao::create($this->dadosBase([
            'responsavel_nome' => 'Nome do Governador',
            'responsavel_cargo' => 'Governador Provincial da Huíla',
        ]));

        $resultado = $this->processar(
            'A: {{INSTITUICAO_RESPONSAVEL_NOME}} | B: {{ INSTITUICAO_RESPONSAVEL_NOME }} | '
            .'C: {{INSTITUICAO_RESPONSAVEL_CARGO}} | D: {{ INSTITUICAO_RESPONSAVEL_CARGO }}',
            $this->utilizadorDeGabinete()
        );

        $this->assertStringContainsString('A: Nome do Governador | B: Nome do Governador', $resultado);
        $this->assertStringContainsString('C: Governador Provincial da Huíla | D: Governador Provincial da Huíla', $resultado);
        $this->assertStringNotContainsString('INSTITUICAO_RESPONSAVEL', $resultado);
    }

    public function test_sem_responsavel_fica_aviso_visivel(): void
    {
        DadosInstituicao::create($this->dadosBase());

        $resultado = $this->processar('{{INSTITUICAO_RESPONSAVEL_NOME}} / {{INSTITUICAO_RESPONSAVEL_CARGO}}', $this->utilizadorDeGabinete());

        $this->assertSame('[Responsável da instituição não definido] / [Cargo do responsável não definido]', $resultado);
    }

    public function test_nome_e_escapado(): void
    {
        DadosInstituicao::create($this->dadosBase(['responsavel_nome' => '<script>alert(1)</script>']));

        $resultado = $this->processar('{{INSTITUICAO_RESPONSAVEL_NOME}}', $this->utilizadorDeGabinete());

        $this->assertStringNotContainsString('<script>', $resultado);
        $this->assertStringContainsString('&lt;script&gt;', $resultado);
    }

    public function test_responsavel_nome_continua_a_ser_o_do_gabinete(): void
    {
        DadosInstituicao::create($this->dadosBase(['responsavel_nome' => 'Nome do Governador']));

        $resultado = $this->processar('{{RESPONSAVEL_NOME}}', $this->utilizadorDeGabinete());

        $this->assertSame('Secretária Geral', $resultado);
    }

    public function test_documento_emitido_mantem_o_governador_da_altura(): void
    {
        $user = $this->utilizadorDeGabinete();
        $instituicao = DadosInstituicao::create($this->dadosBase(['responsavel_nome' => 'Governador Anterior']));
        $especie = DocumentoEspecie::create(['nome' => 'DESPACHO', 'descricao' => 'Despacho', 'ativo' => true]);

        $doc = DocumentoInterno::create([
            'titulo' => 'Despacho',
            'conteudo_final' => $this->processar('<p>{{INSTITUICAO_RESPONSAVEL_NOME}}</p>', $user),
            'status' => 'rascunho',
            'criado_por' => $user->id,
            'departamento_id' => $user->departamento_id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'DESP/001',
        ]);

        $instituicao->update(['responsavel_nome' => 'Novo Governador']);

        $this->assertSame('<p>Governador Anterior</p>', $doc->fresh()->conteudo_final);
        $this->assertSame('<p>Novo Governador</p>', $this->processar('<p>{{INSTITUICAO_RESPONSAVEL_NOME}}</p>', $user));
    }
}
