<?php

namespace App\Policies;

use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PastaPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->isAdmin()) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pasta $pasta): bool
    {
        // Pastas públicas
        if ($pasta->type === 'public') {
            return true;
        }

        // Criador da pasta
        if ((int) $pasta->created_by === (int) $user->id) {
            return true;
        }

        // Departamentos do utilizador
        $permissionService = app(\App\Services\DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);
        if ($pasta->departamento_id && in_array((int) $pasta->departamento_id, $userDeps, true)) {
            return true;
        }

        // Chefe / Super Chefe de Gabinete
        if ($pasta->gabinete_id) {
            if ($user->isSuperChefeGabinete() && $user->gabineteSuperGerenciado && (int) $user->gabineteSuperGerenciado->id === (int) $pasta->gabinete_id) {
                return true;
            }
            if ($user->isChefeGabinete() && $user->gabineteGerenciado && (int) $user->gabineteGerenciado->id === (int) $pasta->gabinete_id) {
                return true;
            }
        }

        // Área de Expediente
        if ($permissionService->isUserInAreaExpediente($user) && $pasta->is_system) {
            return true;
        }

        // Verificação de partilha por departamento
        $sharedMetadata = $pasta->metadata()->where('key', 'shared_departments')->first();
        if ($sharedMetadata) {
            $sharedDepts = json_decode($sharedMetadata->value, true);
            if (is_array($sharedDepts)) {
                $intersect = array_intersect(array_map('intval', $userDeps), array_map('intval', $sharedDepts));
                if (! empty($intersect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Pasta $pasta): bool
    {
        // Pastas do sistema não devem ter nome alterado por utilizadores normais
        if ($pasta->is_system && ! $user->isAdmin()) {
            return false;
        }

        // Criador
        if ((int) $pasta->created_by === (int) $user->id) {
            return true;
        }

        // Chefe de Departamento da pasta
        $permissionService = app(\App\Services\DocumentoPermissionService::class);
        $userDeps = $permissionService->getUserDepartments($user);
        if ($permissionService->isChefeDepartamento($user) && in_array((int) $pasta->departamento_id, $userDeps, true)) {
            return true;
        }

        // Chefe de Gabinete do gabinete da pasta
        if ($pasta->gabinete_id && $user->isChefeGabinete() && optional($user->gabineteGerenciado)->id === (int) $pasta->gabinete_id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Pasta $pasta): bool
    {
        // Pastas do sistema nunca podem ser excluídas por utilizadores normais
        if ($pasta->is_system) {
            return false;
        }

        return $this->update($user, $pasta);
    }
}
