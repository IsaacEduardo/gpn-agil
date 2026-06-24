<?php

namespace App\Policies;

use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentoEntradaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('documentos_entrada.listar');
    }

    public function create(User $user): Response
    {
        // Admin can do everything
        if ($user->role && $user->role->name === 'admin') {
            return Response::allow();
        }

        // Restrict Chefe de Departamento
        if ($user->role && $user->role->name === 'chefe-departamento') {
            return Response::deny('Ação não permitida: Chefes de Departamento não devem registrar entradas de documentos.');
        }

        // Restrict Chefe de Gabinete (Responsible for any Cabinet)
        $permissionService = app(\App\Services\DocumentoPermissionService::class);
        $responsibleGabinetes = $permissionService->getUserResponsibleGabinetes($user);

        if (! empty($responsibleGabinetes)) {
            return Response::deny('Ação não permitida: Chefes de Gabinete não devem registrar entradas de documentos.');
        }

        // Allow standard users
        return Response::allow();
    }

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
        // Admin pode sempre.
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

    /**
     * Determina se o utilizador pode arquivar um documento de entrada.
     * Espelha a regra de DocumentoInternoPolicy::archive adaptada a entradas.
     */
    public function archive(User $user, DocumentoEntrada $documento): bool
    {
        // Admin
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }

        $deps = (method_exists($user, 'departamentos') && $user->departamentos) ? $user->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $user->departamento_id) {
            $deps = [$user->departamento_id];
        }

        // Quem registou o documento
        if ((int) $documento->user_id === (int) $user->id) {
            return true;
        }

        // Chefe de departamento do documento
        if (($user->hasRole('chefe-departamento') || $user->hasRole('chefe_departamento'))
            && in_array((int) $documento->departamento_id, array_map('intval', $deps), true)) {
            return true;
        }

        // Chefe de gabinete responsável pelo gabinete do departamento do documento
        $gabId = optional($documento->departamento)->gabinete_id;
        if ($gabId) {
            $gab = Gabinete::find($gabId);
            if ($gab && (int) $gab->responsavel_id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
