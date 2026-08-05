<?php

namespace App\Policies;

use App\Models\Requerente;
use App\Models\User;

class RequerentePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('requerentes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Requerente $requerente): bool
    {
        return $user->can('requerentes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('requerentes.create') || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Requerente $requerente): bool
    {
        return $user->can('requerentes.edit') || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Requerente $requerente): bool
    {
        return $user->can('requerentes.delete') || $user->isAdmin();
    }
}
