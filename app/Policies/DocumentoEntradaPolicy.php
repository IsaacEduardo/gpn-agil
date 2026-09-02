<?php

namespace App\Policies;

use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class DocumentoEntradaPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('documentos_entrada.listar');
    }

    public function view(User $user, DocumentoEntrada $documento): bool
    {
        return app(DocumentoPermissionService::class)->canViewDocument($user, $documento);
    }

    public function create(User $user): Response
    {
        // Restrict Chefe de Departamento
        if ($user->role && $user->role->name === 'chefe-departamento') {
            return Response::deny('Ação não permitida: Chefes de Departamento não devem registrar entradas de documentos.');
        }

        // Restrict Chefe de Gabinete (Responsible for any Cabinet)
        $permissionService = app(DocumentoPermissionService::class);
        $responsibleGabinetes = $permissionService->getUserResponsibleGabinetes($user);

        if (! empty($responsibleGabinetes)) {
            return Response::deny('Ação não permitida: Chefes de Gabinete não devem registrar entradas de documentos.');
        }

        // Allow standard users
        return Response::allow();
    }

    public function update(User $user, DocumentoEntrada $documento): bool
    {
        // Autor da entrada ou usuário do mesmo departamento (se ainda não tramitado/arquivado)
        if ($documento->arquivado) {
            return false;
        }

        $permissionService = app(DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);

        if ((int) $documento->user_id === (int) $user->id) {
            return true;
        }

        if (in_array((int) $documento->departamento_id, $userDeps, true)) {
            return true;
        }

        // Chefe de Gabinete do departamento
        $gabId = optional($documento->departamento)->gabinete_id;
        if ($gabId && $permissionService->isGabineteResponsavel($user, $gabId)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, DocumentoEntrada $documento): bool
    {
        // Não permitir deletar documentos já tramitados ou arquivados
        if ($documento->arquivado) {
            return false;
        }

        // Apenas quem criou pode excluir o próprio registro (ou admin via before)
        return (int) $documento->user_id === (int) $user->id;
    }

    public function download(User $user, DocumentoEntrada $documento): bool
    {
        return $this->view($user, $documento);
    }

    public function manageAnexos(User $user, DocumentoEntrada $documento): bool
    {
        return $this->update($user, $documento);
    }

    public function vistoAprovar(User $user, DocumentoEntrada $documento): bool
    {
        $isChief = $user->role && ($user->role->name === 'chefe-departamento' || $user->role->name === 'chefe_departamento');
        $permissionService = app(DocumentoPermissionService::class);
        $deps = $permissionService->getUserDepartments($user);

        return $isChief && in_array((int) optional($documento->departamento)->id, $deps, true);
    }

    public function vistoRejeitar(User $user, DocumentoEntrada $documento): bool
    {
        return $this->vistoAprovar($user, $documento);
    }

    public function vistoGabineteAprovar(User $user, DocumentoEntrada $documento): bool
    {
        $gabId = optional($documento->departamento)->gabinete_id;
        $gab = $gabId ? Gabinete::find($gabId) : null;

        return $gab && (int) $gab->responsavel_id === (int) $user->id;
    }

    public function vistoGabineteRejeitar(User $user, DocumentoEntrada $documento): bool
    {
        return $this->vistoGabineteAprovar($user, $documento);
    }

    public function encaminhar(User $user, DocumentoEntrada $documento): bool
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }

        $permissionService = app(DocumentoPermissionService::class);
        $deps = $permissionService->getUserDepartments($user);

        return in_array((int) $documento->departamento_id, $deps, true);
    }

    public function receber(User $user, DocumentoEntrada $documento, int $destinoDepartamentoId): bool
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }

        $permissionService = app(DocumentoPermissionService::class);
        $deps = $permissionService->getUserDepartments($user);

        return in_array((int) $destinoDepartamentoId, $deps, true);
    }

    public function saidaGabinete(User $user, DocumentoEntrada $documento): bool
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }

        $gabId = optional($documento->departamento)->gabinete_id;
        $gab = $gabId ? Gabinete::find($gabId) : null;

        return $gab && (int) $gab->responsavel_id === (int) $user->id;
    }

    /**
     * Determina se o utilizador pode arquivar um documento de entrada.
     */
    public function archive(User $user, DocumentoEntrada $documento): bool
    {
        if ($user->isAdmin() || ($user->role && $user->role->name === 'admin')) {
            return true;
        }
        $permissionService = app(DocumentoPermissionService::class);
        $deps = $permissionService->getUserDepartments($user);

        // Quem registou o documento
        if ((int) $documento->user_id === (int) $user->id) {
            return true;
        }

        // Chefe de departamento do documento
        if (($user->hasRole('chefe-departamento') || $user->hasRole('chefe_departamento'))
            && in_array((int) $documento->departamento_id, array_map('intval', $deps), true)) {
            return true;
        }

        // Chefe de gabinete responsável pelo gabinete do departamento do documento
        $gabId = optional($documento->departamento)->gabinete_id;
        if ($gabId) {
            $gab = Gabinete::find($gabId);
            if ($gab && (int) $gab->responsavel_id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
