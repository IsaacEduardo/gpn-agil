<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SignatureSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    private function generateTestP12(string $password): string
    {
        // Criar arquivo de configuração mínimo do OpenSSL para evitar erros em sistemas Windows sem OPENSSL_CONF configurado
        $cnfFile = tempnam(sys_get_temp_dir(), 'cnf');
        $cnfContent = '[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
prompt = no

[ req_distinguished_name ]
C = AO
ST = Namibe
L = Mocamedes
O = Governo Provincial do Namibe
CN = Carlos Nelson Duarte
emailAddress = carlos@governo.ao
';
        file_put_contents($cnfFile, $cnfContent);

        $configArgs = [
            'config' => $cnfFile,
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Gerar chave privada RSA
        $privateKey = openssl_pkey_new($configArgs);

        $dn = [
            'countryName' => 'AO',
            'stateOrProvinceName' => 'Namibe',
            'localityName' => 'Mocamedes',
            'organizationName' => 'Governo Provincial do Namibe',
            'commonName' => 'Carlos Nelson Duarte',
            'emailAddress' => 'carlos@governo.ao',
        ];

        // Gerar CSR (Certificate Signing Request)
        $csr = openssl_csr_new($dn, $privateKey, $configArgs);

        // Gerar Certificado Auto-assinado
        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, $configArgs);

        $p12Content = '';
        openssl_pkcs12_export($x509, $p12Content, $privateKey, $password);

        @unlink($cnfFile);

        return $p12Content;
    }

    public function test_user_can_upload_and_delete_valid_p12_certificate(): void
    {
        $roles = $this->seedRoles();
        $user = User::factory()->create(['role_id' => $roles['user']->id]);

        $password = 'secret-p12-pass';
        $p12 = $this->generateTestP12($password);

        // Escrever para um arquivo temporário para simular upload
        $tempFile = tempnam(sys_get_temp_dir(), 'p12');
        file_put_contents($tempFile, $p12);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'certificate.p12',
            'application/x-pkcs12',
            null,
            true
        );

        // 1. Enviar certificado com senha ERRADA
        $this->actingAs($user)
            ->post(route('profile.certificate.upload'), [
                'certificate_file' => $uploadedFile,
                'certificate_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors(['certificate_password']);

        // 2. Enviar com a senha CORRETA
        $this->actingAs($user)
            ->post(route('profile.certificate.upload'), [
                'certificate_file' => $uploadedFile,
                'certificate_password' => $password,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Certificado digital associado com sucesso!');

        $this->assertDatabaseHas('user_certificates', [
            'user_id' => $user->id,
            'issuer' => 'Carlos Nelson Duarte',
        ]);

        $cert = UserCertificate::where('user_id', $user->id)->first();
        // A senha do P12 NÃO deve ser persistida (apenas validada no upload).
        $this->assertArrayNotHasKey('p12_password', $cert->getAttributes());
        $this->assertNotNull($cert->public_key);
        $this->assertNotNull($cert->encrypted_p12);

        // 3. Deletar o certificado
        $this->actingAs($user)
            ->delete(route('profile.certificate.destroy'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Certificado digital removido com sucesso!');

        $this->assertDatabaseMissing('user_certificates', [
            'user_id' => $user->id,
        ]);

        @unlink($tempFile);
    }

    public function test_batch_signing_optimizations_and_transaction_safety(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create([
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'password' => Hash::make('my-login-password'),
        ]);
        $gab->update(['responsavel_id' => $chefe->id]);

        $esp = DocumentoEspecie::create(['nome' => 'OFÍCIO', 'ativo' => true]);

        $doc1 = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio 1',
            'conteudo_final' => 'Conteudo 1',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-001',
        ]);

        $doc2 = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio 2',
            'conteudo_final' => 'Conteudo 2',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-002',
        ]);

        // 1. Batch sign com senha ERRADA (deve falhar a validação de primeira e não assinar nada)
        $this->actingAs($chefe)
            ->post(route('gabinete.batch-sign'), [
                'documento_ids' => [$doc1->id, $doc2->id],
                'password' => 'wrong-login-password',
            ])
            ->assertSessionHasErrors(['password']);

        $doc1->refresh();
        $doc2->refresh();
        $this->assertNull($doc1->assinado_em);
        $this->assertNull($doc2->assinado_em);

        // 2. Batch sign com senha CORRETA (deve assinar ambos)
        $this->actingAs($chefe)
            ->post(route('gabinete.batch-sign'), [
                'documento_ids' => [$doc1->id, $doc2->id],
                'password' => 'my-login-password',
            ])
            ->assertRedirect();

        $doc1->refresh();
        $doc2->refresh();
        $this->assertNotNull($doc1->assinado_em);
        $this->assertNotNull($doc2->assinado_em);
        $this->assertEquals($chefe->id, $doc1->assinado_por_user_id);
    }

    public function test_batch_signing_transaction_rolls_back_on_cryptographic_error(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create([
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'password' => Hash::make('my-login-password'),
        ]);
        $gab->update(['responsavel_id' => $chefe->id]);

        $esp = DocumentoEspecie::create(['nome' => 'OFÍCIO', 'ativo' => true]);

        $doc1 = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio 1',
            'conteudo_final' => 'Conteudo 1',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-001',
        ]);

        $doc2 = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio 2',
            'conteudo_final' => 'Conteudo 2',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-002',
        ]);

        // Cadastra um certificado deliberadamente CORROMPIDO no banco de dados para forçar um erro OpenSSL
        UserCertificate::create([
            'user_id' => $chefe->id,
            'encrypted_p12' => 'corrupted-p12-data',
            'public_key' => 'somekey',
            'issuer' => 'Test Issuer',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
        ]);

        // Assinar em lote: como o certificado está corrompido, a assinatura deve abortar e reverter a transação inteira
        $this->actingAs($chefe)
            ->post(route('gabinete.batch-sign'), [
                'documento_ids' => [$doc1->id, $doc2->id],
                'password' => 'my-login-password',
            ])
            ->assertSessionHas('error');

        $doc1->refresh();
        $doc2->refresh();
        $this->assertNull($doc1->assinado_em);
        $this->assertNull($doc2->assinado_em);
    }

    public function test_public_document_verification_page(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create([
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'password' => Hash::make('my-login-password'),
        ]);
        $gab->update(['responsavel_id' => $chefe->id]);

        $esp = DocumentoEspecie::create(['nome' => 'OFÍCIO', 'ativo' => true]);

        $doc = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio de Teste',
            'conteudo_final' => 'Dados do Ofício',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-003',
        ]);

        // Assina o documento (via hash simples)
        $signatureService = app(\App\Services\SignatureService::class);
        $signatureService->sign($doc, $chefe, 'my-login-password');
        $doc->refresh();

        // 1. Verificar documento com hash CORRETO (Público)
        $this->get(route('documentos-internos.verificar', $doc->assinatura_hash))
            ->assertStatus(200)
            ->assertSee('Documento Autêntico')
            ->assertSee('OF-003')
            ->assertSee('Oficio de Teste');

        // 2. Verificar com hash INCORRETO
        $this->get(route('documentos-internos.verificar', 'wrong-hash'))
            ->assertStatus(200)
            ->assertSee('Validação Falhou')
            ->assertSee('Documento não encontrado');
    }

    public function test_signing_requires_and_uses_certificate_password_at_sign_time(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create([
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'password' => Hash::make('my-login-password'),
        ]);
        $gab->update(['responsavel_id' => $chefe->id]);

        // Certificado protegido por senha, armazenado SEM a senha (apenas o P12 cifrado).
        $certPassword = 'p12-secret-pass';
        $p12 = $this->generateTestP12($certPassword);
        UserCertificate::create([
            'user_id' => $chefe->id,
            'encrypted_p12' => $p12, // cast 'encrypted' cifra em repouso
            'public_key' => 'somekey',
            'issuer' => 'Carlos Nelson Duarte',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
        ]);

        $esp = DocumentoEspecie::create(['nome' => 'OFÍCIO', 'ativo' => true]);
        $doc = DocumentoInterno::create([
            'documento_especie_id' => $esp->id,
            'titulo' => 'Oficio Cert',
            'conteudo_final' => 'Conteudo cert',
            'departamento_id' => $dep->id,
            'criado_por' => $chefe->id,
            'status' => \App\Enums\DocumentoStatus::APROVADO,
            'numero_referencia' => 'OF-CERT-001',
        ]);

        $signatureService = app(\App\Services\SignatureService::class);

        // 1. Sem a senha do certificado -> falha (P12 protegido por senha).
        try {
            $signatureService->sign($doc, $chefe, 'my-login-password', false, null);
            $this->fail('A assinatura deveria falhar sem a senha do certificado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('certificate', $e->errors());
        }
        $doc->refresh();
        $this->assertNull($doc->assinado_em);

        // 2. Com a senha correta -> assina com assinatura digital REAL (base64, não o hash simples de 64 hex).
        $signatureService->sign($doc, $chefe, 'my-login-password', false, $certPassword);
        $doc->refresh();
        $this->assertNotNull($doc->assinado_em);
        $this->assertEquals($chefe->id, $doc->assinado_por_user_id);
        $this->assertSame(
            0,
            preg_match('/^[a-f0-9]{64}$/', $doc->assinatura_hash),
            'A assinatura deveria ser uma assinatura digital real (base64), não o hash simples SHA-256.'
        );
    }
}
