<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Chefia de departamento — fonte única.
 *
 * O Chefe de Departamento é o utilizador com o papel "chefe-departamento" designado para esse
 * departamento na ficha do utilizador (Gestão de Utilizadores). Um departamento tem no máximo
 * um chefe; departamentos.responsavel_id é mantido em sincronia por este serviço e é o que
 * a Nota imprime e usa para decidir quem assina (Departamento::chefe()).
 */
class DepartamentoChefiaService
{
    public function roleChefeId(): ?int
    {
        $id = Role::where('name', 'chefe-departamento')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Antes de gravar: recusa designar um segundo chefe para o mesmo departamento.
     *
     * @throws ValidationException
     */
    public function validarDesignacao(?User $user, $roleId, $departamentoId): void
    {
        $roleChefe = $this->roleChefeId();
        if (! $roleChefe || (int) $roleId !== $roleChefe || empty($departamentoId)) {
            return;
        }

        $outro = $this->chefeDesignado((int) $departamentoId, $user?->id);
        if ($outro) {
            $sigla = Departamento::whereKey($departamentoId)->value('sigla') ?: 'seleccionado';

            throw ValidationException::withMessages([
                'role_id' => "O departamento {$sigla} já tem Chefe de Departamento ({$outro->name}). Retire primeiro essa designação.",
            ]);
        }
    }

    /**
     * Depois de gravar: responsavel_id reflecte a designação do utilizador. Liberta os
     * departamentos que deixou de chefiar (papel retirado ou mudança de departamento).
     */
    public function sincronizar(User $user): void
    {
        $roleChefe = $this->roleChefeId();
        $depChefiado = ($roleChefe && (int) $user->role_id === $roleChefe && $user->departamento_id)
            ? (int) $user->departamento_id
            : null;

        Departamento::where('responsavel_id', $user->id)
            ->when($depChefiado, fn ($q) => $q->where('id', '!=', $depChefiado))
            ->update(['responsavel_id' => null]);

        if ($depChefiado) {
            Departamento::whereKey($depChefiado)->update(['responsavel_id' => $user->id]);
        }
    }

    /** Utilizador apagado: deixa de chefiar. */
    public function libertar(User $user): void
    {
        Departamento::where('responsavel_id', $user->id)->update(['responsavel_id' => null]);
    }

    /**
     * Chefe actualmente designado (outro que não $excepto): o responsável gravado ou um
     * utilizador com o papel e esse departamento (registos anteriores à sincronia).
     */
    private function chefeDesignado(int $departamentoId, ?int $excepto): ?User
    {
        $responsavelId = Departamento::whereKey($departamentoId)->value('responsavel_id');
        if ($responsavelId && (int) $responsavelId !== (int) $excepto && ($u = User::find($responsavelId))) {
            return $u;
        }

        return User::where('departamento_id', $departamentoId)
            ->where('role_id', $this->roleChefeId())
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->first();
    }
}
