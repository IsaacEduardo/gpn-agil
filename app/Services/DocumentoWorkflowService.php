<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\User;
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

        // TODO: Disparar notificação para o chefe do departamento
        // Notification::send($chefe, new DocumentoParaAnalise($doc));

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

        // Logar o motivo no histórico ou auditoria
        // $doc->logAudit('rejeicao', null, ['motivo' => $motivo]);

        return $doc;
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
