<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Validation\ValidationException;

class DocumentoWorkflowService
{
    protected $versioningService;

    public function __construct(DocumentoInternoService $versioningService)
    {
        $this->versioningService = $versioningService;
    }

    /**
     * Envia o documento para análise (Chefia).
     */
    public function submitForReview(DocumentoInterno $doc, User $user): DocumentoInterno
    {
        if ($doc->status !== DocumentoStatus::RASCUNHO) {
            throw ValidationException::withMessages(['status' => 'Apenas rascunhos podem ser enviados para análise.']);
        }

        // Incrementa Minor Version (1.0.x -> 1.1.0)
        // Mas espere, Rascunho -> Análise é mudança de estado. A versão muda quando o conteúdo muda.
        // Se não houver mudança de conteúdo, mantemos a versão ou incrementamos patch?
        // Vamos assumir que enviar para análise "congela" a versão de rascunho atual.

        $doc->update([
            'status' => DocumentoStatus::EM_ANALISE,
        ]);

        // Notifica a chefia: o chefe designado do departamento ou, num documento do
        // gabinete, o responsável do gabinete (antes ninguém era avisado).
        $chefe = app(DocumentoPermissionService::class)->chefiaANotificar($doc);
        if ($chefe && $chefe->id !== $user->id) {
            $chefe->notify(new SimpleBroadcastNotification(
                'Documento para análise: '.$doc->titulo,
                $user->name.' enviou o documento '.$this->numeroDoc($doc).' para a sua análise.',
                route('documentos-internos.show', $doc->id),
                'high',
                'documento_enviado_analise',
            ));
        }

        return $doc;
    }

    /**
     * Aprova o documento (Chefe aprova o teor).
     */
    public function approve(DocumentoInterno $doc, User $user): DocumentoInterno
    {
        if ($doc->status !== DocumentoStatus::EM_ANALISE) {
            throw ValidationException::withMessages(['status' => 'O documento não está em análise.']);
        }

        // Validar se usuário é chefe (Lógica simplificada, deve usar Gate/Policy)
        // if (!$user->isChief()) throw ...

        $doc->update([
            'status' => DocumentoStatus::APROVADO,
            'bloqueado_edicao' => true, // Bloqueia para garantir integridade até a assinatura
        ]);

        // Notifica o autor de que o seu documento foi aprovado.
        $autor = $doc->autor;
        if ($autor && $autor->id !== $user->id) {
            $autor->notify(new SimpleBroadcastNotification(
                'Documento aprovado: '.$doc->titulo,
                $user->name.' aprovou o documento '.$this->numeroDoc($doc).'.',
                route('documentos-internos.show', $doc->id),
                'normal',
                'documento_aprovado',
            ));
        }

        return $doc;
    }

    /**
     * Rejeita/Devolve o documento para correções.
     */
    public function reject(DocumentoInterno $doc, User $user, string $motivo): DocumentoInterno
    {
        // Assinado nunca volta atrás — nem pelo admin, que o Gate deixa passar na policy.
        if (! in_array($doc->status, [DocumentoStatus::EM_ANALISE, DocumentoStatus::APROVADO]) || $doc->assinado_em) {
            throw ValidationException::withMessages(['status' => 'Status inválido para rejeição.']);
        }

        $estadoAnterior = $doc->status->value;

        $doc->update([
            'status' => DocumentoStatus::RASCUNHO,
            'bloqueado_edicao' => false,
        ]);

        // O motivo fica no registo de auditoria e não só na notificação.
        $doc->logAudit('devolucao', ['status' => $estadoAnterior], ['status' => DocumentoStatus::RASCUNHO->value], $motivo);

        // Notifica o autor de que o seu documento foi devolvido, com o motivo.
        $autor = $doc->autor;
        if ($autor && $autor->id !== $user->id) {
            $autor->notify(new SimpleBroadcastNotification(
                'Documento devolvido para correção: '.$doc->titulo,
                $user->name.' devolveu o documento '.$this->numeroDoc($doc).'. Motivo: '.$motivo,
                route('documentos-internos.show', $doc->id),
                'high',
                'documento_devolvido',
            ));
        }

        return $doc;
    }

    /**
     * Avisa o autor de que a chefia alterou o seu documento durante a análise.
     * A alteração em si fica no histórico de versões com o nome de quem editou.
     */
    public function notificarEdicaoPelaChefia(DocumentoInterno $doc, User $editor): void
    {
        $autor = $doc->autor;
        if ($doc->status !== DocumentoStatus::EM_ANALISE || ! $autor || $autor->id === $editor->id) {
            return;
        }

        $autor->notify(new SimpleBroadcastNotification(
            'Documento alterado pela chefia: '.$doc->titulo,
            $editor->name.' alterou o documento '.$this->numeroDoc($doc).' durante a análise. Consulte o histórico de versões.',
            route('documentos-internos.show', $doc->id),
            'normal',
            'documento_editado_chefia',
        ));
    }

    /**
     * Rótulo curto do documento para o corpo da notificação (número de referência ou #id).
     */
    private function numeroDoc(DocumentoInterno $doc): string
    {
        return $doc->numero_referencia ?: ('#'.$doc->id);
    }

    /**
     * Arquiva o documento.
     */
    public function archive(DocumentoInterno $doc, User $user): DocumentoInterno
    {
        if (! in_array($doc->status, [DocumentoStatus::ASSINADO, DocumentoStatus::RECEBIDO, DocumentoStatus::ENCAMINHADO_EXTERNO])) {
            throw ValidationException::withMessages(['status' => 'Apenas documentos finalizados podem ser arquivados.']);
        }

        $doc->update([
            'status' => DocumentoStatus::ARQUIVADO,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $user->id,
        ]);

        return $doc;
    }
}
