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
            $userGabId = optional($user->departamento)->gabinete_id;
            if ($userGabId && optional($doc->departamento)->gabinete_id === $userGabId) {
                return true;
            }
        }

        return false;
    }

    public function download(User $user, DocumentoInterno $doc)
    {
        return $this->view($user, $doc);
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
        if ($doc->status !== DocumentoStatus::RASCUNHO && $doc->status !== 'rascunho') {
            return false;
        }

        // Autor
        if ($doc->criado_por === $user->id) {
            return true;
        }

        // Chefe de Depto do mesmo departamento
        $permissionService = app(DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);
        if ($permissionService->isChefeDepartamento($user) && in_array((int) $doc->departamento_id, $userDeps, true)) {
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
        return (int) $doc->criado_por === (int) $user->id;
    }

    // Ações de Workflow

    public function approve(User $user, DocumentoInterno $doc)
    {
        if ($doc->status !== DocumentoStatus::EM_ANALISE && $doc->status !== 'em_analise') {
            return false;
        }

        $permissionService = app(DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);

        // Chefe do Departamento do documento
        if ($permissionService->isChefeDepartamento($user) && in_array((int) $doc->departamento_id, $userDeps, true)) {
            return true;
        }

        // Chefe de Gabinete também pode aprovar (override)
        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($gabinete && optional($doc->departamento)->gabinete_id === $gabinete->id) {
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
        // Owner can archive own draft
        if ($doc->criado_por === $user->id && ($doc->status === DocumentoStatus::RASCUNHO || $doc->status === 'rascunho')) {
            return true;
        }

        $permissionService = app(DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);

        // Department head can archive documents of their department
        if ($permissionService->isChefeDepartamento($user) && in_array((int) $doc->departamento_id, $userDeps, true)) {
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
