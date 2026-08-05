<?php

namespace App\Policies;

use App\Models\SolicitacaoAtribuicao;
use App\Models\User;

class SolicitacaoAtribuicaoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('solicitacoes_lotes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SolicitacaoAtribuicao $solicitacao): bool
    {
        return $user->can('solicitacoes_lotes.view') || $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('solicitacoes_lotes.create') || $user->isAdmin();
    }

    /**
     * Instrução do processo: vincular lote, registar laudos e transitar estados
     * intermédios. Não cobre a homologação — ver homologar().
     */
    public function update(User $user, SolicitacaoAtribuicao $solicitacao): bool
    {
        return $user->can('solicitacoes_lotes.analisar') || $user->isAdmin();
    }

    /**
     * Decisão final do processo: aprovar/rejeitar e emitir o Termo de Atribuição.
     */
    public function homologar(User $user, SolicitacaoAtribuicao $solicitacao): bool
    {
        return $user->can('solicitacoes_lotes.homologar') || $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Não existe permissão dedicada para eliminação, pelo que se exige o nível
     * mais alto do módulo (homologação).
     */
    public function delete(User $user, SolicitacaoAtribuicao $solicitacao): bool
    {
        return $user->can('solicitacoes_lotes.homologar') || $user->isAdmin();
    }
}
