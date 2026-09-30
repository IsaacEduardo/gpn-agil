<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;

/**
 * Consultar empresas é livre para autenticados (os formulários de requisição
 * usam a lista e o json); criar, editar e apagar é do admin ou de quem tiver
 * a permissão 'empresas.gerir' (atribuível na Matriz de Acesso).
 */
class EmpresaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Empresa $empresa): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->podeGerir($user);
    }

    public function update(User $user, Empresa $empresa): bool
    {
        return $this->podeGerir($user);
    }

    public function delete(User $user, Empresa $empresa): bool
    {
        return $this->podeGerir($user);
    }

    private function podeGerir(User $user): bool
    {
        return $user->isAdmin() || $user->can('empresas.gerir');
    }
}
