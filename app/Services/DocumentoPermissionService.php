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

        $roleName = $user->role ? $user->role->name : null;
        if (in_array($roleName, ['chefe-departamento', 'chefe_departamento', UserRole::CHEFE_DEPARTAMENTO->value], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('chefe-departamento') || $user->hasRole('chefe_departamento')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifica o perfil de fluxo de trabalho do utilizador (gabinete, expediente, chefe_departamento, tecnico).
     */
    public function getUserWorkflowProfile(User $user): string
    {
        // 1. Chefe de Gabinete / Admin
        if ($this->isAdmin($user) || count($this->getUserResponsibleGabinetes($user)) > 0) {
            return 'gabinete';
        }
        if (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete()) {
            return 'gabinete';
        }

        // 2. Área de Expediente do Gabinete
        if ($this->isUserInAreaExpediente($user)) {
            return 'expediente';
        }

        // 3. Chefe de Departamento
        if ($this->isChefeDepartamento($user)) {
            return 'chefe_departamento';
        }
        $userDeps = $this->getUserDepartments($user);
        if (count($userDeps) && \App\Models\Departamento::whereIn('id', $userDeps)->where('responsavel_id', $user->id)->exists()) {
            return 'chefe_departamento';
        }

        // 4. Técnico de Departamento (Default)
        return 'tecnico';
    }

    /**
     * Verifica se o usuário pertence à Área de Expediente do Gabinete.
     */
    public function isUserInAreaExpediente(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);
        if (empty($userDeps)) {
            return false;
        }

        return \App\Models\Departamento::whereIn('id', $userDeps)
            ->where('is_area_expediente', true)
            ->exists();
    }

    /**
     * Verifica se o usuário tem permissão para despachar o documento (Chefe de Gabinete / Responsável).
     */
    public function canDespachar(User $user, DocumentoEntrada $documento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;
        if ($docGabId) {
            if (method_exists($user, 'isSuperChefeDoGabinete') && $user->isSuperChefeDoGabinete($docGabId)) {
                return true;
            }
            if ($this->isGabineteResponsavel($user, $docGabId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se o usuário tem permissão para encaminhar documento tratado (Chefe de Gabinete ou Expediente).
     */
    public function canEncaminharTratado(User $user, DocumentoEntrada $documento): bool
    {
        if ($this->canDespachar($user, $documento)) {
            return true;
        }

        return $this->isUserInAreaExpediente($user);
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
     * Quem pode concluir uma tarefa: o utilizador a quem foi atribuída, quem
     * gere tarefas no documento, ou qualquer membro do departamento a que a
     * tarefa foi atribuída.
     *
     * Fonte única partilhada pelo endpoint e pela view — existiam três versões
     * desta regra (endpoint concluir, endpoint cancelar e show.blade.php) e
     * tinham divergido.
     */
    public function canConcluirTarefa(User $user, $documento, \App\Models\DocumentoTarefa $tarefa): bool
    {
        if ($tarefa->assigned_to_user_id && (int) $tarefa->assigned_to_user_id === (int) $user->id) {
            return true;
        }

        if ($this->canManageTasks($user, $documento)) {
            return true;
        }

        if ($tarefa->assigned_to_departamento_id) {
            return in_array((int) $tarefa->assigned_to_departamento_id, $this->getUserDepartments($user), true);
        }

        return false;
    }

    /**
     * Quem pode cancelar uma tarefa: quem a atribuiu ou quem gere tarefas no
     * documento.
     */
    public function canCancelarTarefa(User $user, $documento, \App\Models\DocumentoTarefa $tarefa): bool
    {
        if ((int) $tarefa->assigned_by_id === (int) $user->id) {
            return true;
        }

        return $this->canManageTasks($user, $documento);
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

        // 0. Criador do documento
        if ((int) $documento->user_id === (int) $user->id) {
            return true;
        }

        // 1. Super Chefe do Gabinete
        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;
        if ($docGabId && method_exists($user, 'isSuperChefeDoGabinete') && $user->isSuperChefeDoGabinete($docGabId)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);

        // 2. Departamento Atual do documento
        if (in_array((int) $documento->departamento_id, $userDeps)) {
            return true;
        }

        // 3. Responsável pelo Gabinete
        if ($docGabId && $this->isGabineteResponsavel($user, $docGabId)) {
            return true;
        }

        // 4. Departamentos de Destino (Despacho / Encaminhamento Múltiplo)
        if (count($userDeps) && $documento->departamentosDestino()->whereIn('departamentos.id', $userDeps)->exists()) {
            return true;
        }

        // 5. Histórico de Encaminhamentos
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

        // 6. Possui Tarefa/Despacho atribuído ao utilizador ou ao seu departamento
        $hasTask = \App\Models\DocumentoTarefa::where('documento_entrada_id', $documento->id)
            ->where(function ($q) use ($user, $userDeps) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhere('assigned_by_id', $user->id)
                  ->orWhere('responsavel_user_id', $user->id);
                if (count($userDeps)) {
                    $q->orWhereIn('assigned_to_departamento_id', $userDeps);
                }
            })->exists();

        if ($hasTask) {
            return true;
        }

        return false;
    }
}
