<?php

namespace App\Services;

use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class EdmsStorageService
{
    /**
     * Armazena um arquivo com criptografia e versionamento.
     */
    public function storeDocumentVersion(DocumentoInterno $documento, UploadedFile $file, User $user, string $changeLog = ''): DocumentoVersao
    {
        // 1. Calcular Hash e Metadados
        $content = $file->get();
        $hash = hash('sha256', $content);
        $size = $file->getSize();
        $mime = $file->getMimeType();
        $originalName = $file->getClientOriginalName();

        // 2. Definir caminho de armazenamento
        // Estrutura: edms/{gabinete_id}/{departamento_id}/{ano}/{hash}.enc
        $gabineteId = $documento->departamento->gabinete_id ?? 'global';
        $deptId = $documento->departamento_id;
        $year = date('Y');
        $path = "edms/{$gabineteId}/{$deptId}/{$year}/{$hash}.enc";

        // 3. Criptografar e Salvar (se ainda não existir)
        if (! Storage::exists($path)) {
            $encryptedContent = Crypt::encrypt($content);
            Storage::put($path, $encryptedContent);
        }

        // 4. Determinar número da versão
        $lastVersion = $documento->versoes()->first();
        $major = $lastVersion ? $lastVersion->major : 1;
        $minor = $lastVersion ? $lastVersion->minor + 1 : 0;
        $patch = 0;

        // Se for a primeira versão
        if (! $lastVersion) {
            $major = 1;
            $minor = 0;
        }

        // 5. Criar registro de versão
        $versao = DocumentoVersao::create([
            'documento_interno_id' => $documento->id,
            'versao' => ($major * 10000) + ($minor * 100) + $patch, // Legacy integer
            'major' => $major,
            'minor' => $minor,
            'patch' => $patch,
            'titulo' => $documento->titulo, // Snapshot do título
            'conteudo_final' => $documento->conteudo_final, // Snapshot do HTML se houver
            'caminho_arquivo' => $path,
            'checksum' => $hash,
            'tamanho_bytes' => $size,
            'mime_type' => $mime,
            'change_log' => $changeLog ?: 'Nova versão de arquivo carregada.',
            'criado_por' => $user->id,
        ]);

        // 6. Atualizar documento principal
        $documento->update([
            'versao_atual' => $versao->versao,
            'versao_major' => $major,
            'versao_minor' => $minor,
            'versao_patch' => $patch,
        ]);

        return $versao;
    }

    /**
     * Recupera o conteúdo descriptografado de uma versão.
     */
    public function retrieveFileContent(DocumentoVersao $versao): ?string
    {
        if (! $versao->caminho_arquivo || ! Storage::exists($versao->caminho_arquivo)) {
            return null;
        }

        $encrypted = Storage::get($versao->caminho_arquivo);
        try {
            return Crypt::decrypt($encrypted);
        } catch (\Exception $e) {
            // Log error
            return null;
        }
    }

    /**
     * Gera uma URL temporária ou resposta de download.
     */
    public function downloadResponse(DocumentoVersao $versao)
    {
        $content = $this->retrieveFileContent($versao);

        if (! $content) {
            abort(404, 'Arquivo não encontrado ou corrompido.');
        }

        // Audit Log
        // (Assumindo que AuditLog seja tratado via Eventos ou Controller, mas idealmente aqui também)

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $versao->titulo.'.'.$this->getExtensionFromMime($versao->mime_type));
    }

    private function getExtensionFromMime($mime)
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $map[$mime] ?? 'dat';
    }
}
