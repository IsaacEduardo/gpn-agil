<?php

namespace Tests\Feature;

use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstituicaoConfigTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);

        return compact('admin', 'user');
    }

    public function test_guest_cannot_access_instituicao_config(): void
    {
        $this->get(route('admin.instituicao.edit'))
            ->assertRedirect('/login');

        $this->put(route('admin.instituicao.update'), [])
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_instituicao_config(): void
    {
        $roles = $this->seedRoles();
        $user = User::factory()->create(['role_id' => $roles['user']->id]);

        $this->actingAs($user)
            ->get(route('admin.instituicao.edit'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->put(route('admin.instituicao.update'), [
                'nome_oficial' => 'Governo de Teste',
                'sigla' => 'GDT',
                'cidade' => 'Luanda',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_access_instituicao_config(): void
    {
        $roles = $this->seedRoles();
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);

        $this->actingAs($admin)
            ->get(route('admin.instituicao.edit'))
            ->assertStatus(200)
            ->assertViewIs('instituicao.edit')
            ->assertViewHas('dados');
    }

    public function test_admin_can_update_instituicao_config(): void
    {
        $roles = $this->seedRoles();
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);

        $postData = [
            'nome_oficial' => 'Governo Provincial da Huíla',
            'sigla' => 'GPH',
            'cidade' => 'Lubango',
            'nif' => '500123456',
            'telefone' => '+244 923 000 000',
            'email' => 'huila@governo.ao',
            'endereco' => 'Praça Agostinho Neto, Lubango',
            'cabecalho_linha1' => 'REPÚBLICA DE ANGOLA',
            'cabecalho_linha2' => 'GOVERNO PROVINCIAL DA HUÍLA',
            'cabecalho_linha3' => 'GABINETE DO GOVERNADOR',
            'rodape_texto' => 'Contactos oficiais da Huíla',
        ];

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $postData)
            ->assertRedirect(route('admin.instituicao.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dados_instituicao', [
            'nome_oficial' => 'Governo Provincial da Huíla',
            'sigla' => 'GPH',
            'cidade' => 'Lubango',
            'nif' => '500123456',
        ]);
    }

    public function test_admin_can_upload_images(): void
    {
        Storage::fake('public');
        $roles = $this->seedRoles();
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);

        $logo = UploadedFile::fake()->image('logo.png');
        $rodapeImg = UploadedFile::fake()->image('rodape.jpg');

        $postData = [
            'nome_oficial' => 'Governo Provincial do Namibe',
            'sigla' => 'GPN',
            'cidade' => 'Moçâmedes',
            'logo' => $logo,
            'rodape_img' => $rodapeImg,
        ];

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $postData)
            ->assertRedirect(route('admin.instituicao.edit'));

        $dados = DadosInstituicao::first();
        $this->assertNotNull($dados->logo_path);
        $this->assertNotNull($dados->rodape_img_path);

        Storage::disk('public')->assertExists($dados->logo_path);
        Storage::disk('public')->assertExists($dados->rodape_img_path);

        // Verify public paths & base64 / paths
        $this->assertStringContainsString('storage/'.$dados->logo_path, $dados->logo_url);
        $this->assertStringContainsString('storage/'.$dados->rodape_img_path, $dados->rodape_url);
        $this->assertStringContainsString($dados->logo_path, $dados->logo_absolute_path);
        $this->assertStringContainsString($dados->rodape_img_path, $dados->rodape_absolute_path);
    }

    public function test_template_placeholders_resolved_correctly(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gabinete de Teste']);
        $dep = Departamento::create(['nome' => 'DLP', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);

        DadosInstituicao::create([
            'nome_oficial' => 'Governo Provincial do Namibe',
            'sigla' => 'GPN',
            'cidade' => 'Moçâmedes',
            'cabecalho_linha1' => 'REPÚBLICA DE ANGOLA',
            'cabecalho_linha2' => 'GOVERNO PROVINCIAL DO NAMIBE',
            'cabecalho_linha3' => 'GABINETE DO GOVERNADOR',
        ]);

        $template = 'Instituição: {{INSTITUICAO_NOME}} | Cab1: {{INSTITUICAO_CABECALHO_1}} | Cab2: {{INSTITUICAO_CABECALHO_2}} | Cab3: {{INSTITUICAO_CABECALHO_3}} | Local: {{INSTITUICAO_LOCAL}}';

        $service = app(DocumentoInternoService::class);
        $processed = $service->processarTemplate($template, null, $user);

        $this->assertStringContainsString('Governo Provincial do Namibe', $processed);
        $this->assertStringContainsString('REPÚBLICA DE ANGOLA', $processed);
        $this->assertStringContainsString('GOVERNO PROVINCIAL DO NAMIBE', $processed);
        $this->assertStringContainsString('GABINETE DO GOVERNADOR', $processed);
        $this->assertStringContainsString('Moçâmedes', $processed);
    }
}
