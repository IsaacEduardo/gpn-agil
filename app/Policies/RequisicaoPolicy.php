<?php

namespace App\Policies;

use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\User;

class RequisicaoPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin'))
            || $user->hasPermission('requisicoes.view_any')
            || $user->hasPermission('requisicoes.view')
            // Chefe de departamento com permissão de visto deve poder acessar a lista
            || $user->hasPermission('visto_departamento_requisicoes');
    }

    public function view(User $user, Requisicao $requisicao): bool
    {
        if ($user->role && ($user->role->name === 'admin')) {
            return true;
        }
        if ($user->hasPermission('requisicoes.view_any')) {
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

    public function create(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin'))
            || $user->hasPermission('requisicoes.create');
    }

    public function reviewDepartamentoAny(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin'))
            || $user->hasPermission('visto_departamento_requisicoes');
    }

    public function approve(User $user, Requisicao $requisicao): bool
    {
        if ($user->role && ($user->role->name === 'admin')) {
            return true;
        }
        if ($user->hasPermission('aprovar_requisicoes')) {
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
