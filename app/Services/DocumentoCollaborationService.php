<?php

namespace App\Services;

use App\Enums\NivelColaboracao;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoCollabUpdate;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Núcleo da edição colaborativa em tempo real de Documentos Internos.
 *
 * Princípio: o Y.Doc (Yjs/CRDT) é a fonte viva durante a sessão, mas o artefacto persistido
 * canónico continua a ser `conteudo_final` (HTML). O autosave persiste HTML de forma silenciosa
 * (sem versão/auditoria/reindex); o versionamento e efeitos colaterais ocorrem no checkpoint.
 */
class DocumentoCollaborationService
{
    public function __construct(private DocumentoInternoService $documentoService) {}

    /**
     * Resolve o nível efetivo de colaboração de um utilizador para um documento.
     * Retorna null se não tiver qualquer acesso colaborativo.
     */
    public function nivelDe(User $user, DocumentoInterno $doc): ?NivelColaboracao
    {
        // Admin e autor administram o próprio documento.
        if ($user->isAdmin() || $doc->criado_por === $user->id) {
            return NivelColaboracao::ADMINISTRAR;
        }

        // Colaborador explicitamente convidado.
        $colab = $doc->colaboradores()->where('user_id', $user->id)->first();
        if ($colab) {
            return $colab->nivel;
        }

        // Chefe de departamento do documento pode editar (alinhado com DocumentoInternoPolicy::update).
        if (($user->hasRole('chefe_departamento') || $user->hasRole('chefe-departamento'))
            && $doc->departamento_id === $user->departamento_id) {
            return NivelColaboracao::EDITAR;
        }

        return null;
    }

    public function podeColaborar(User $user, DocumentoInterno $doc): bool
    {
        return $this->nivelDe($user, $doc) !== null;
    }

    public function podeEditar(User $user, DocumentoInterno $doc): bool
    {
        $nivel = $this->nivelDe($user, $doc);

        return $nivel !== null && $nivel->podeEditar();
    }

    public function podeAdministrar(User $user, DocumentoInterno $doc): bool
    {
        $nivel = $this->nivelDe($user, $doc);

        return $nivel !== null && $nivel->podeAdministrar();
    }

    /**
     * Estado inicial para o cliente: log de updates Yjs (base64, por ordem) e HTML de fallback.
     *
     * @return array{updates: array<int, string>, html: string, hasState: bool}
     */
    public function estadoInicial(DocumentoInterno $doc): array
    {
        $updates = DocumentoCollabUpdate::where('documento_interno_id', $doc->id)
            ->orderBy('id')
            ->pluck('update')
            ->all();

        return [
            'updates' => $updates,
            'html' => $doc->conteudo_final ?? '',
            'hasState' => count($updates) > 0,
        ];
    }

    public function registarUpdate(DocumentoInterno $doc, ?User $user, string $base64Update): DocumentoCollabUpdate
    {
        return DocumentoCollabUpdate::create([
            'documento_interno_id' => $doc->id,
            'user_id' => $user?->id,
            'update' => $base64Update,
            'is_snapshot' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Autosave: persiste o HTML corrente SEM criar versão nem disparar reindex/auditoria
     * (saveQuietly). Garante durabilidade contínua durante a sessão.
     */
    public function autosave(DocumentoInterno $doc, string $html): void
    {
        $doc->conteudo_final = Sanitizer::clean($html);
        $doc->saveQuietly();
    }

    /**
     * Persiste o título/assunto sem criar versão (last-write-wins; baixa contenção).
     */
    public function salvarTitulo(DocumentoInterno $doc, string $titulo): void
    {
        $doc->titulo = $titulo;
        $doc->saveQuietly();
    }

    /**
     * Checkpoint: materializa o HTML, cria uma versão (updateWithVersioning) e — quando fornecido
     * um snapshot Yjs completo — compacta o log num único registo. Dispara auditoria/reindex.
     */
    public function checkpoint(
        DocumentoInterno $doc,
        User $user,
        string $html,
        string $changeType = 'minor',
        ?string $changeLog = null,
        ?string $snapshotBase64 = null,
        ?string $titulo = null,
    ): DocumentoInterno {
        return DB::transaction(function () use ($doc, $user, $html, $changeType, $changeLog, $snapshotBase64, $titulo) {
            $doc = $this->documentoService->updateWithVersioning(
                $doc,
                [
                    'titulo' => $titulo ?: $doc->titulo,
                    'conteudo_final' => Sanitizer::clean($html),
                ],
                $user,
                in_array($changeType, ['major', 'minor', 'patch'], true) ? $changeType : 'minor',
                $changeLog
            );

            if ($snapshotBase64 !== null) {
                DocumentoCollabUpdate::where('documento_interno_id', $doc->id)->delete();
                DocumentoCollabUpdate::create([
                    'documento_interno_id' => $doc->id,
                    'user_id' => $user->id,
                    'update' => $snapshotBase64,
                    'is_snapshot' => true,
                    'created_at' => now(),
                ]);
            }

            return $doc;
        });
    }

    /**
     * Convida (ou atualiza o nível de) um colaborador do MESMO gabinete do documento.
     *
     * @throws \InvalidArgumentException se o convidado não pertencer ao gabinete do documento.
     */
    public function convidar(DocumentoInterno $doc, User $inviter, User $invitee, NivelColaboracao $nivel): DocumentoColaborador
    {
        if (! $this->mesmoGabinete($doc, $invitee)) {
            throw new \InvalidArgumentException('Só é possível convidar utilizadores do mesmo gabinete do documento.');
        }

        return DocumentoColaborador::updateOrCreate(
            ['documento_interno_id' => $doc->id, 'user_id' => $invitee->id],
            ['nivel' => $nivel->value, 'convidado_por' => $inviter->id]
        );
    }

    public function definirNivel(DocumentoInterno $doc, User $invitee, NivelColaboracao $nivel): void
    {
        DocumentoColaborador::where('documento_interno_id', $doc->id)
            ->where('user_id', $invitee->id)
            ->update(['nivel' => $nivel->value]);
    }

    public function remover(DocumentoInterno $doc, User $invitee): void
    {
        DocumentoColaborador::where('documento_interno_id', $doc->id)
            ->where('user_id', $invitee->id)
            ->delete();
    }

    /**
     * Verdadeiro se o utilizador pertence ao mesmo gabinete do documento (via departamento).
     */
    public function mesmoGabinete(DocumentoInterno $doc, User $user): bool
    {
        $docGab = optional($doc->departamento)->gabinete_id;
        $userGab = optional($user->departamento)->gabinete_id;

        return $docGab !== null && $userGab !== null && (int) $docGab === (int) $userGab;
    }

    /**
     * Cor estável (hex) por utilizador, para cursores/seleção identificados.
     */
    public function corDoUtilizador(int $userId): string
    {
        $paleta = ['#e6194B', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#008080', '#9A6324', '#800000', '#808000', '#000075'];

        return $paleta[$userId % count($paleta)];
    }
}
