<?php

namespace App\Policies;

use App\Models\ReservaEspaco;
use App\Models\User;

class ReservaEspacoPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin' || $user->role->name === 'user'))
            || $user->hasPermission('reservas.view_any')
            || $user->hasPermission('reservas.view');
    }

    public function create(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin' || $user->role->name === 'user'))
            || $user->hasPermission('reservas.create');
    }

    public function reviewDepartamentoAny(User $user): bool
    {
        return ($user->role && ($user->role->name === 'admin'))
            || $user->hasPermission('visto_departamento_reservas');
    }

    public function approve(User $user, ReservaEspaco $reserva): bool
    {
        if ($user->role && ($user->role->name === 'admin')) {
            return true;
        }
        if ($user->hasPermission('aprovar_reservas')) {
            return true;
        }

        return false;
    }

    public function reject(User $user, ReservaEspaco $reserva): bool
    {
        return $this->approve($user, $reserva);
    }

    public function vistoAprovar(User $user, ReservaEspaco $reserva): bool
    {
        if (! $user->hasPermission('visto_departamento_reservas')) {
            return false;
        }
        if ($user->role && $user->role->name === 'chefe-departamento') {
            $reserva->loadMissing('usuario');
            if ($reserva->usuario && $user->departamento_id !== $reserva->usuario->departamento_id) {
                return false;
            }
        }

        return true;
    }

    public function vistoRejeitar(User $user, ReservaEspaco $reserva): bool
    {
        return $this->vistoAprovar($user, $reserva);
    }
}
