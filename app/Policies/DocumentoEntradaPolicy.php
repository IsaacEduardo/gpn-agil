<?php

namespace App\Policies;

use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;

class DocumentoEntradaPolicy
{
    public function vistoAprovar(User $user, DocumentoEntrada $documento): bool
    {
        $isChief = $user->role && $user->role->name === 'chefe-departamento';
        $deps = (method_exists($user, 'departamentos') && $user->departamentos) ? $user->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $user->departamento_id) {
            $deps = [$user->departamento_id];
        }

        return $isChief && in_array((int) optional($documento->departamento)->id, $deps);
    }

    public function vistoRejeitar(User $user, DocumentoEntrada $documento): bool
    {
        return $this->vistoAprovar($user, $documento);
    }

    public function vistoGabineteAprovar(User $user, DocumentoEntrada $documento): bool
    {
        $gabId = optional($documento->departamento)->gabinete_id;
        $gab = $gabId ? Gabinete::find($gabId) : null;

        return $gab && (int) $gab->responsavel_id === (int) $user->id;
    }

    public function vistoGabineteRejeitar(User $user, DocumentoEntrada $documento): bool
    {
        return $this->vistoGabineteAprovar($user, $documento);
    }

    public function encaminhar(User $user, DocumentoEntrada $documento): bool
    {
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }
        $deps = (method_exists($user, 'departamentos') && $user->departamentos) ? $user->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $user->departamento_id) {
            $deps = [$user->departamento_id];
        }

        return in_array((int) $documento->departamento_id, $deps);
    }

    public function receber(User $user, DocumentoEntrada $documento, int $destinoDepartamentoId): bool
    {
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }
        $deps = (method_exists($user, 'departamentos') && $user->departamentos) ? $user->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $user->departamento_id) {
            $deps = [$user->departamento_id];
        }

        return in_array((int) $destinoDepartamentoId, $deps);
    }

    public function saidaGabinete(User $user, DocumentoEntrada $documento): bool
    {
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }
        $gabId = optional($documento->departamento)->gabinete_id;
        $gab = $gabId ? Gabinete::find($gabId) : null;

        return $gab && (int) $gab->responsavel_id === (int) $user->id;
    }
}
