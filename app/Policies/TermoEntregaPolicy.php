<?php

namespace App\Policies;

use App\Models\TermoEntrega;
use App\Models\User;

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
     * Determine whether the user can create Credenciais.
     */
    public function createCredencial(User $user): bool
    {
        return $user->can('credenciais.gerir') || $user->isAdmin();
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
