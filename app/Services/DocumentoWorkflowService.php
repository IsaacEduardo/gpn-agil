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

        // Notifica o chefe do departamento de que há um documento a aguardar análise.
        $chefe = $doc->departamento?->chefe;
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
        if (! in_array($doc->status, [DocumentoStatus::EM_ANALISE, DocumentoStatus::APROVADO])) {
            throw ValidationException::withMessages(['status' => 'Status inválido para rejeição.']);
        }

        $doc->update([
            'status' => DocumentoStatus::RASCUNHO,
            'bloqueado_edicao' => false,
        ]);

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

        // Logar o motivo no histórico ou auditoria
        // $doc->logAudit('rejeicao', null, ['motivo' => $motivo]);

        return $doc;
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
