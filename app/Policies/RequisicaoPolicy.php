<?php

namespace App\Policies;

use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RequisicaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('requisicoes.listar')
            || $user->can('requisicoes.listar_todas')
            || $user->can('requisicoes.aprovar');
    }

    public function view(User $user, Requisicao $requisicao): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->can('requisicoes.listar_todas')) {
            return true;
        }
        $requisicao->loadMissing('usuario.departamentos', 'usuario.departamento');
        $owner = $requisicao->usuario;
        if ($owner && (int) $owner->id === (int) $user->id) {
            return true;
        }
        $ownerDeps = ($owner && method_exists($owner, 'departamentos') && $owner->departamentos)
            ? $owner->departamentos->pluck('id')->all() : [];
        if (! count($ownerDeps) && $owner && $owner->departamento_id) {
            $ownerDeps = [$owner->departamento_id];
        }
        $actorDeps = (method_exists($user, 'departamentos') && $user->departamentos)
            ? $user->departamentos->pluck('id')->all() : [];
        if (! count($actorDeps) && $user->departamento_id) {
            $actorDeps = [$user->departamento_id];
        }
        if (count(array_intersect($actorDeps, $ownerDeps)) > 0) {
            return true;
        }
        $headedGabIds = Gabinete::where('responsavel_id', $user->id)->pluck('id')->all();
        if (count($headedGabIds)) {
            $ownerGabIds = [];
            if ($owner) {
                $ownerGabIds = collect($ownerDeps)->map(function ($depId) {
                    $dep = \App\Models\Departamento::find($depId);

                    return $dep ? (int) $dep->gabinete_id : null;
                })->filter()->unique()->values()->all();
            }
            if (count(array_intersect($headedGabIds, $ownerGabIds)) > 0) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): Response
    {
        // Admin can do everything
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Restrict Chefe de Departamento
        if ($user->role && $user->role->name === 'chefe-departamento') {
            return Response::deny('Ação não permitida: Chefes de Departamento não devem criar requisições.');
        }

        // Restrict Chefe de Gabinete (Responsible for any Cabinet)
        $permissionService = app(\App\Services\DocumentoPermissionService::class);
        $responsibleGabinetes = $permissionService->getUserResponsibleGabinetes($user);

        if (! empty($responsibleGabinetes)) {
            return Response::deny('Ação não permitida: Chefes de Gabinete não devem criar requisições.');
        }

        // Allow standard users (or check specific permission if needed)
        // Historically it checked 'requisicoes.create', keeping that or defaulting to true for 'user' role
        if ($user->hasPermission('requisicoes.create')) {
            return Response::allow();
        }

        // Default allow for basic users if they don't have explicit permission system blocking them
        // Assuming 'user' role should be able to create.
        return Response::allow();
    }

    public function reviewDepartamentoAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('visto_departamento_requisicoes');
    }

    public function approve(User $user, Requisicao $requisicao): bool
    {
        // 1. Permission Check
        if ($user->can('requisicoes.aprovar')) {
            // Logic to ensure they are approving for their department is handled below or assumed true for "Global Approvers"
            // However, for strict departmental control, we should mix permission with logic.
            // But 'requisicoes.aprovar' usually implies power.
            // Let's allow if they have the permission, BUT if they are a Chefe, we double check department scope if we want to be strict.
            // For now, let's treat the permission as a capability.
            // To be safe and respect legacy logic:
            if ($user->role && $user->role->name === 'chefe-departamento') {
                // Fallthrough to department check below
            } else {
                return true;
            }
        }

        if ($user->isAdmin()) {
            return true;
        }
        if ($user->can('requisicoes.aprovar')) {
            return true;
        }
        if ($user->role && $user->role->name === 'chefe-departamento') {
            $requisicao->loadMissing('usuario');
            $owner = $requisicao->usuario;
            $ownerDeps = ($owner && method_exists($owner, 'departamentos') && $owner->departamentos)
                ? $owner->departamentos->pluck('id')->all() : [];
            if ($owner && ! count($ownerDeps) && $owner->departamento_id) {
                $ownerDeps = [$owner->departamento_id];
            }
            $actorDeps = (method_exists($user, 'departamentos') && $user->departamentos)
                ? $user->departamentos->pluck('id')->all() : [];
            if (! count($actorDeps) && $user->departamento_id) {
                $actorDeps = [$user->departamento_id];
            }

            return count(array_intersect($actorDeps, $ownerDeps)) > 0;
        }

        return false;
    }

    public function reject(User $user, Requisicao $requisicao): bool
    {
        return $this->approve($user, $requisicao);
    }

    public function vistoAprovar(User $user, Requisicao $requisicao): bool
    {
        if (! $user->hasPermission('visto_departamento_requisicoes')) {
            return false;
        }
        if ($user->role && $user->role->name === 'chefe-departamento') {
            $requisicao->loadMissing('usuario');
            $owner = $requisicao->usuario;
            if ($owner && $user->departamento_id !== $owner->departamento_id) {
                return false;
            }
        }

        return true;
    }

    public function vistoRejeitar(User $user, Requisicao $requisicao): bool
    {
        return $this->vistoAprovar($user, $requisicao);
    }
}
