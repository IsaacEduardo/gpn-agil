<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viatura;

class ViaturaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('viaturas.listar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Viatura $viatura): bool
    {
        return $user->can('viaturas.listar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('viaturas.gerir') || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Viatura $viatura): bool
    {
        return $user->can('viaturas.gerir') || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Viatura $viatura): bool
    {
        return $user->can('viaturas.gerir') || $user->isAdmin();
    }
}
