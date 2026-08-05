<?php

namespace App\Policies;

use App\Models\Lote;
use App\Models\User;

class LotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('lotes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lote $lote): bool
    {
        return $user->can('lotes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('lotes.create') || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lote $lote): bool
    {
        return $user->can('lotes.edit') || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lote $lote): bool
    {
        return $user->can('lotes.delete') || $user->isAdmin();
    }
}
