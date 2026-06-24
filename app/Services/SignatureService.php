<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Enums\UserRole;
use App\Models\DocumentoInterno;
use App\Models\Requisicao;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SignatureService
{
    /**
     * Assina digitalmente um documento.
     *
     * @param  Model  $documento  (DocumentoInterno ou Requisicao)
     * @param  string  $password  Senha da conta do utilizador (autenticação da ação)
     * @param  string|null  $certificatePassword  Senha do certificado P12, fornecida no momento da
     *                                            assinatura. Nunca é persistida no banco de dados.
     *
     * @throws ValidationException
     */
    public function sign(Model $documento, User $user, string $password, bool $skipPasswordCheck = false, ?string $certificatePassword = null): Model
    {
        // 1. Validar Senha do Usuário (se não for explicitamente pulada para otimização em lote)
        if (! $skipPasswordCheck) {
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['A senha informada está incorreta.'],
                ]);
            }
        }

        // 2. Validar Permissão de Assinatura
        if (! $this->canSign($documento, $user)) {
            throw ValidationException::withMessages([
                'authorization' => ['Você não tem permissão para assinar este documento.'],
            ]);
        }

        // 3. Verificar Status (Se for Documento Interno)
        if ($documento instanceof DocumentoInterno) {
            if ($documento->status !== DocumentoStatus::APROVADO) {
                throw ValidationException::withMessages([
                    'status' => 'O documento deve estar APROVADO para ser assinado.',
                ]);
            }
        }

        // 4. Integridade e Certificado Digital
        $timestamp = now();
        $content = '';
        if ($documento instanceof DocumentoInterno) {
            $content = $documento->conteudo_final;
        } elseif ($documento instanceof Requisicao) {
            $content = $documento->observacoes.$documento->tipo->value;
        }

        // Gera Hash de Integridade (SHA-256)
        $contentHash = hash('sha256', $content);
        $signatureString = "DOC:{$documento->id}|TS:{$timestamp}|USER:{$user->id}|CONTENT_HASH:{$contentHash}";

        // Tentar assinar com Certificado Digital (se existir)
        $certificate = UserCertificate::where('user_id', $user->id)->latest()->first();
        if ($certificate && $certificate->isValid()) {
            try {
                // O P12 é descriptografado automaticamente pelo cast 'encrypted' do modelo.
                $p12 = $certificate->encrypted_p12;
                // A senha do P12 é fornecida no momento da assinatura (nunca é persistida).
                $p12Password = $certificatePassword ?? '';

                // Ler certificado usando a senha do P12 (ou vazio como padrão)
                $certs = [];
                if (! openssl_pkcs12_read($p12, $certs, $p12Password)) {
                    throw new \RuntimeException('Senha do certificado digital P12 incorreta ou não informada.');
                }

                $privateKey = $certs['pkey'];
                $signature = '';
                if (! openssl_sign($signatureString, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                    throw new \RuntimeException('Falha ao gerar assinatura digital com a chave privada.');
                }

                $finalHash = base64_encode($signature); // Armazena a assinatura digital real
            } catch (\Exception $e) {
                // Abortar e reportar a falha em vez de cair silenciosamente em hash simples
                throw ValidationException::withMessages([
                    'certificate' => ['Erro na assinatura digital com certificado: '.$e->getMessage()],
                ]);
            }
        } else {
            // Fallback para hash simples apenas quando o usuário NÃO tem um certificado registrado
            $finalHash = hash('sha256', $signatureString);
        }

        // 5. Salvar Assinatura e Atualizar Estado
        $updateData = [
            'assinado_em' => $timestamp,
            'assinado_por_user_id' => $user->id,
            'assinatura_hash' => $finalHash,
            'bloqueado_edicao' => true,
        ];

        if ($documento instanceof DocumentoInterno) {
            $updateData['status'] = DocumentoStatus::ASSINADO;
            // Incrementa Major Version (x.x.x -> Y.0.0) ao assinar (publicar)
            $documento->versao_major += 1;
            $documento->versao_minor = 0;
            $documento->versao_patch = 0;
            $updateData['versao_major'] = $documento->versao_major;
            $updateData['versao_minor'] = 0;
            $updateData['versao_patch'] = 0;
        }

        $documento->update($updateData);

        return $documento;
    }

    /**
     * Verifica se o usuário pode assinar o documento.
     */
    public function canSign(Model $documento, User $user): bool
    {
        if ($user->hasRole(UserRole::ADMIN->value)) {
            return true;
        }

        if ($documento instanceof DocumentoInterno) {
            return $this->canSignDocumentoInterno($documento, $user);
        }

        if ($documento instanceof Requisicao) {
            return $this->canSignRequisicao($documento, $user);
        }

        return false;
    }

    protected function canSignDocumentoInterno(DocumentoInterno $doc, User $user): bool
    {
        if ($doc->assinado_em) {
            return false;
        }

        $especie = $doc->especie ? strtoupper($doc->especie->nome) : '';

        // PARECER: Autor
        if ($especie === 'PARECER') {
            return $doc->criado_por === $user->id;
        }

        // OFICIAIS (Gabinete): Chefe de Gabinete
        $oficiais = ['OFÍCIO', 'OFICIO', 'CARTA', 'MEMORANDO', 'CIRCULAR', 'DESPACHO'];
        if (in_array($especie, $oficiais)) {
            if (! $doc->departamento || ! $doc->departamento->gabinete) {
                return false;
            }

            return $doc->departamento->gabinete->responsavel_id === $user->id;
        }

        // NOTA / REQUERIMENTO / DECLARAÇÃO: Chefe de Departamento
        if (in_array($especie, ['NOTA', 'REQUERIMENTO', 'DECLARAÇÃO', 'DECLARACAO'])) {
            return $user->hasRole(UserRole::CHEFE_DEPARTAMENTO->value) &&
                   ($user->departamento_id === $doc->departamento_id ||
                    $user->departamentos->contains($doc->departamento_id));
        }

        // Fallback: Se o usuário for Chefe de Gabinete e o documento pertencer ao gabinete dele, permitir assinatura (override)
        if ($doc->departamento && $doc->departamento->gabinete && $doc->departamento->gabinete->responsavel_id === $user->id) {
            return true;
        }

        return false;
    }

    protected function canSignRequisicao(Requisicao $req, User $user): bool
    {
        if ($req->assinado_em) {
            return false;
        }
        $requester = $req->usuario;
        if (! $requester) {
            return false;
        }
        $deptId = $requester->departamento_id;

        return $user->hasRole(UserRole::CHEFE_DEPARTAMENTO->value) &&
               ($user->departamento_id === $deptId || $user->departamentos->contains($deptId));
    }
}
