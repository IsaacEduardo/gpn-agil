<?php

namespace App\Policies;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\DocumentoCollaborationService;
use App\Services\SignatureService;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoInternoPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin()) {
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

        // Mesmo Departamento
        if ($doc->departamento_id === $user->departamento_id) {
            return true;
        }

        // Super Chefe de Gabinete (Se o depto do doc pertence ao gabinete dele)
        if ($user->isSuperChefeGabinete()) {
            $gabinete = $user->gabineteSuperGerenciado;
            if ($gabinete && $doc->departamento && $doc->departamento->gabinete_id === $gabinete->id) {
                return true;
            }
        }

        // Chefe de Gabinete (Se o depto do doc pertence ao gabinete dele)
        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($doc->departamento && $doc->departamento->gabinete_id === $gabinete->id) {
                return true;
            }
        }

        // Permissão delegada
        if ($user->hasPermissionTo('gabinete.view_all')) {
            if ($user->departamento && $doc->departamento->gabinete_id === $user->departamento->gabinete_id) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, DocumentoInterno $doc)
    {
        if ($doc->bloqueado_edicao) {
            return false;
        }

        // Apenas rascunhos podem ser editados (regra geral)
        if ($doc->status !== DocumentoStatus::RASCUNHO) {
            return false;
        }

        // Autor ou Chefe Depto
        if ($doc->criado_por === $user->id) {
            return true;
        }

        // Chefe de Depto pode editar? Geralmente não, ele aprova/rejeita. Mas vamos permitir edição corretiva.
        if (($user->hasRole('chefe_departamento') || $user->hasRole('chefe-departamento')) && $doc->departamento_id === $user->departamento_id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, DocumentoInterno $doc)
    {
        if ($doc->bloqueado_edicao) {
            return false;
        }

        // Apenas Autor pode excluir rascunho
        return $doc->criado_por === $user->id;
    }

    // Ações de Workflow

    public function approve(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::EM_ANALISE) {
            return false;
        }

        // Chefe do Departamento do documento
        if (($user->hasRole('chefe_departamento') || $user->hasRole('chefe-departamento')) && $doc->departamento_id === $user->departamento_id) {
            return true;
        }

        // Chefe de Gabinete também pode aprovar (override)
        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($doc->departamento->gabinete_id === $gabinete->id) {
                return true;
            }
        }

        return false;
    }

    public function sign(User $user, DocumentoInterno $doc)
    {
        return app(SignatureService::class)->canSign($doc, $user);
    }

    /**
     * Determine if the user may archive the document.
     */
    public function archive(User $user, DocumentoInterno $doc)
    {
        // Admins can archive any
        if ($user->isAdmin()) {
            return true;
        }
        // Owner can archive own draft
        if ($doc->criado_por === $user->id && $doc->status === DocumentoStatus::RASCUNHO) {
            return true;
        }
        // Department head can archive documents of their department
        if (($user->hasRole('chefe_departamento') || $user->hasRole('chefe-departamento')) && $doc->departamento_id === $user->departamento_id) {
            return true;
        }
        // Chefe de Gabinete can archive documents belonging to his gabinete
        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($gabinete && optional($doc->departamento)->gabinete_id === $gabinete->id) {
                return true;
            }
        }

        return false;
    }

    // Edição colaborativa em tempo real

    /**
     * Pode participar numa sessão colaborativa (presença/edição) do documento.
     * Restrito a rascunhos não bloqueados; o nível efetivo é resolvido pelo serviço.
     */
    public function collaborate(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::RASCUNHO || $doc->bloqueado_edicao) {
            return false;
        }

        return app(DocumentoCollaborationService::class)->podeColaborar($user, $doc);
    }

    /**
     * Pode gerir colaboradores (convidar, alterar nível, remover). Apenas nível Administrar.
     */
    public function manageCollaborators(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::RASCUNHO || $doc->bloqueado_edicao) {
            return false;
        }

        return app(DocumentoCollaborationService::class)->podeAdministrar($user, $doc);
    }
}
