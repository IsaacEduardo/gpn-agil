<?php

namespace App\Policies;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\DocumentoCollaborationService;
use App\Services\DocumentoPermissionService;
use App\Services\SignatureService;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoInternoPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, DocumentoInterno $doc)
    {
        // Autor sempre pode
        if ($doc->criado_por === $user->id) {
            return true;
        }

        // Departamentos do utilizador
        $permissionService = app(DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);

        if ($doc->departamento_id && in_array((int) $doc->departamento_id, $userDeps, true)) {
            return true;
        }

        // Super Chefe de Gabinete (Se o depto do doc pertence ao gabinete dele)
        if ($user->isSuperChefeGabinete()) {
            $gabinete = $user->gabineteSuperGerenciado;
            if ($gabinete && $doc->gabineteEmissorId() === (int) $gabinete->id) {
                return true;
            }
        }

        // Chefe de Gabinete (Se o depto do doc pertence ao gabinete dele)
        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($gabinete && $doc->gabineteEmissorId() === (int) $gabinete->id) {
                return true;
            }
        }

        // Permissão delegada
        if ($user->hasPermissionTo('gabinete.view_all')) {
            $userGabId = optional($user->departamento)->gabinete_id;
            if ($userGabId && $doc->gabineteEmissorId() === (int) $userGabId) {
                return true;
            }
        }

        // Colaborador convidado: o convite é por gabinete, e o convidado de outro
        // departamento editava pelo link sem conseguir ver a ficha nem o PDF.
        return $doc->colaboradores()->where('user_id', $user->id)->exists();
    }

    public function download(User $user, DocumentoInterno $doc)
    {
        return $this->view($user, $doc);
    }

    public function create(User $user)
    {
        return true;
    }

    /**
     * Em rascunho editam o autor e a chefia; em análise só a chefia (corrige em vez
     * de devolver). Aprovado, assinado ou arquivado: ninguém — para corrigir um
     * aprovado devolve-se primeiro (ver reject).
     */
    public function update(User $user, DocumentoInterno $doc)
    {
        if (! $doc->aceitaEdicao()) {
            return false;
        }

        $chefia = fn () => app(DocumentoPermissionService::class)->chefiaDoDocumentoInterno($user, $doc);

        return match ($doc->status) {
            DocumentoStatus::RASCUNHO => (int) $doc->criado_por === (int) $user->id || $chefia(),
            DocumentoStatus::EM_ANALISE => $chefia(),
            default => false,
        };
    }

    public function delete(User $user, DocumentoInterno $doc)
    {
        if ($doc->bloqueado_edicao) {
            return false;
        }

        // Apenas Autor pode excluir rascunho
        return (int) $doc->criado_por === (int) $user->id;
    }

    // Ações de Workflow

    public function approve(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::EM_ANALISE) {
            return false;
        }

        return app(DocumentoPermissionService::class)->chefiaDoDocumentoInterno($user, $doc);
    }

    /**
     * Devolver ao autor (volta a rascunho, desbloqueado). Em análise: a chefia.
     * Aprovado e ainda por assinar: a chefia ou quem o vai assinar — é a única saída
     * para um erro detetado depois da aprovação. Assinado: nunca.
     */
    public function reject(User $user, DocumentoInterno $doc)
    {
        if ($doc->assinado_em) {
            return false;
        }

        $chefia = app(DocumentoPermissionService::class)->chefiaDoDocumentoInterno($user, $doc);

        return match ($doc->status) {
            DocumentoStatus::EM_ANALISE => $chefia,
            DocumentoStatus::APROVADO => $chefia || app(SignatureService::class)->canSign($doc, $user),
            default => false,
        };
    }

    public function sign(User $user, DocumentoInterno $doc)
    {
        return app(SignatureService::class)->canSign($doc, $user);
    }

    /**
     * Determina se o utilizador pode arquivar o documento.
     * Regra única em DocumentoPermissionService::podeArquivar (só técnicos com
     * a guarda do documento — decisão provisória de 2026-09-29).
     */
    public function archive(User $user, DocumentoInterno $doc)
    {
        return app(DocumentoPermissionService::class)->podeArquivar($user, $doc);
    }

    // Edição colaborativa em tempo real

    /**
     * Pode participar numa sessão colaborativa (presença/edição) do documento.
     * Restrito a rascunhos não bloqueados; o nível efetivo é resolvido pelo serviço.
     */
    public function collaborate(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::RASCUNHO && $doc->status !== 'rascunho' || $doc->bloqueado_edicao) {
            return false;
        }

        return app(DocumentoCollaborationService::class)->podeColaborar($user, $doc);
    }

    /**
     * Pode gerir colaboradores (convidar, alterar nível, remover). Apenas nível Administrar.
     */
    public function manageCollaborators(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::RASCUNHO && $doc->status !== 'rascunho' || $doc->bloqueado_edicao) {
            return false;
        }

        return app(DocumentoCollaborationService::class)->podeAdministrar($user, $doc);
    }
}
