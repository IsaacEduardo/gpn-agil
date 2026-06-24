<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DocumentoPermissionService
{
    /**
     * Get all department IDs the user belongs to.
     */
    public function getUserDepartments(User $user): array
    {
        return Cache::remember("user_{$user->id}_departments", 300, function () use ($user) {
            $deps = [];

            if ($user->departamento_id) {
                $deps[] = (int) $user->departamento_id;
            }

            if (method_exists($user, 'departamentos')) {
                $user->loadMissing('departamentos');
                if ($user->departamentos) {
                    $secondary = $user->departamentos->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $deps = array_merge($deps, $secondary);
                }
            }

            return array_values(array_unique($deps));
        });
    }

    /**
     * Get all Cabinet IDs where the user is responsible.
     */
    public function getUserResponsibleGabinetes(User $user): array
    {
        return Cache::remember("user_{$user->id}_responsible_gabinetes", 300, function () use ($user) {
            return Gabinete::where('responsavel_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        });
    }

    public function isAdmin(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role && $user->role->name === UserRole::ADMIN->value;
    }

    public function isChefeDepartamento(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role && $user->role->name === UserRole::CHEFE_DEPARTAMENTO->value;
    }

    public function canReceiveInDepartment(User $user, int $targetDepartamentoId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);

        return in_array($targetDepartamentoId, $userDeps);
    }

    /**
     * Check if user has permission to manage tasks for a document.
     */
    public function canManageTasks(User $user, $documento): bool
    {
        $docDepId = (int) $documento->departamento_id;
        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;

        // 0. Super Cabinet Chief
        if ($docGabId && $user->isSuperChefeDoGabinete($docGabId)) {
            return true;
        }

        // 1. Cabinet Responsible
        $userGabinetes = $this->getUserResponsibleGabinetes($user);
        if ($docGabId && in_array($docGabId, $userGabinetes)) {
            return true;
        }

        // 2. Department Chief (and belongs to the document's department)
        if ($this->isChefeDepartamento($user)) {
            $userDeps = $this->getUserDepartments($user);
            if (in_array($docDepId, $userDeps)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user is responsible for a specific cabinet.
     */
    public function isGabineteResponsavel(User $user, ?int $gabineteId): bool
    {
        if (! $gabineteId) {
            return false;
        }
        $responsibleGabinetes = $this->getUserResponsibleGabinetes($user);

        return in_array($gabineteId, $responsibleGabinetes);
    }

    /**
     * Check if the user has permission to view/download the document.
     */
    public function canViewDocument(User $user, DocumentoEntrada $documento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // 0. Super Cabinet Chief
        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;
        if ($docGabId && $user->isSuperChefeDoGabinete($docGabId)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);

        // 1. Current Department
        if (in_array((int) $documento->departamento_id, $userDeps)) {
            return true;
        }

        // 2. Cabinet Responsible
        if ($docGabId && $this->isGabineteResponsavel($user, $docGabId)) {
            return true;
        }

        // 3. Document History (User's department handled it)
        if (count($userDeps)) {
            $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->where(function ($q) use ($userDeps) {
                    $q->whereIn('origem_departamento_id', $userDeps)
                        ->orWhereIn('destino_departamento_id', $userDeps);
                })->exists();

            if ($hasHistory) {
                return true;
            }
        }

        return false;
    }
}
