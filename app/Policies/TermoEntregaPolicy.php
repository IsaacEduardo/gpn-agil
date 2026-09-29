<?php

namespace App\Policies;

use App\Models\Departamento;
use App\Models\TermoEntrega;
use App\Models\User;
use App\Services\DocumentoPermissionService;

class TermoEntregaPolicy
{
    /**
     * Determine whether the user can view any models (General Terms).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('termos.listar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view any Credenciais.
     */
    public function viewAnyCredencial(User $user): bool
    {
        return $user->can('credenciais.listar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TermoEntrega $termo): bool
    {
        if ($termo->tipo === 'credencial') {
            return $user->can('credenciais.listar') || $user->isAdmin();
        }

        return $user->can('termos.listar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can create models (General Terms).
     */
    public function create(User $user): bool
    {
        return $user->can('termos.gerir') || $user->isAdmin();
    }

    /**
     * Emitir credenciais: só os utilizadores do Departamento de Logística e
     * Património (sigla em config('documentos.credenciais_departamento_sigla'))
     * e o admin. Decisão do cliente, 2026-09-29.
     */
    public function createCredencial(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $sigla = (string) config('documentos.credenciais_departamento_sigla', 'DLP');
        $meus = app(DocumentoPermissionService::class)->getUserDepartments($user);

        return $sigla !== '' && $meus !== []
            && Departamento::whereIn('id', $meus)->where('sigla', $sigla)->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TermoEntrega $termo): bool
    {
        if ($termo->tipo === 'credencial') {
            return $user->can('credenciais.gerir') || $user->isAdmin();
        }

        return $user->can('termos.gerir') || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TermoEntrega $termo): bool
    {
        if ($termo->tipo === 'credencial') {
            return $user->can('credenciais.gerir') || $user->isAdmin();
        }

        return $user->can('termos.gerir') || $user->isAdmin();
    }
}
