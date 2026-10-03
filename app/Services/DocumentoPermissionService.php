<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DocumentoPermissionService
{
    /**
     * Get all department IDs the user belongs to.
     */
    public function getUserDepartments(User $user): array
    {
        return Cache::remember("user_{$user->id}_departments", 300, function () use ($user) {
            $deps = [];

            if ($user->departamento_id) {
                $deps[] = (int) $user->departamento_id;
            }

            if (method_exists($user, 'departamentos')) {
                $user->loadMissing('departamentos');
                if ($user->departamentos) {
                    $secondary = $user->departamentos->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $deps = array_merge($deps, $secondary);
                }
            }

            return array_values(array_unique($deps));
        });
    }

    /**
     * Get all Cabinet IDs where the user is responsible.
     */
    public function getUserResponsibleGabinetes(User $user): array
    {
        return Cache::remember("user_{$user->id}_responsible_gabinetes", 300, function () use ($user) {
            return Gabinete::where('responsavel_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        });
    }

    public function isAdmin(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role && $user->role->name === UserRole::ADMIN->value;
    }

    public function isChefeDepartamento(User $user): bool
    {
        $user->loadMissing('role');

        $roleName = $user->role ? $user->role->name : null;
        if (in_array($roleName, ['chefe-departamento', 'chefe_departamento', UserRole::CHEFE_DEPARTAMENTO->value], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('chefe-departamento') || $user->hasRole('chefe_departamento')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifica o perfil de fluxo de trabalho do utilizador (gabinete, expediente, chefe_departamento, tecnico).
     */
    public function getUserWorkflowProfile(User $user): string
    {
        // 1. Chefe de Gabinete / Admin
        if ($this->isAdmin($user) || count($this->getUserResponsibleGabinetes($user)) > 0) {
            return 'gabinete';
        }
        if (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete()) {
            return 'gabinete';
        }

        // 2. Área de Expediente do Gabinete
        if ($this->isUserInAreaExpediente($user)) {
            return 'expediente';
        }

        // 3. Chefe de Departamento
        if ($this->isChefeDepartamento($user)) {
            return 'chefe_departamento';
        }
        $userDeps = $this->getUserDepartments($user);
        if (count($userDeps) && \App\Models\Departamento::whereIn('id', $userDeps)->where('responsavel_id', $user->id)->exists()) {
            return 'chefe_departamento';
        }

        // 4. Técnico de Departamento (Default)
        return 'tecnico';
    }

    /**
     * Verifica se o usuário pertence à Área de Expediente do Gabinete.
     */
    public function isUserInAreaExpediente(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);
        if (empty($userDeps)) {
            return false;
        }

        return \App\Models\Departamento::whereIn('id', $userDeps)
            ->where('is_area_expediente', true)
            ->exists();
    }

    /**
     * Verifica se o usuário tem permissão para despachar o documento (Chefe de Gabinete / Responsável).
     */
    public function canDespachar(User $user, DocumentoEntrada $documento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;
        if ($docGabId) {
            if (method_exists($user, 'isSuperChefeDoGabinete') && $user->isSuperChefeDoGabinete($docGabId)) {
                return true;
            }
            if ($this->isGabineteResponsavel($user, $docGabId)) {
                return true;
            }
        }

        return false;
    }

    public function canReceiveInDepartment(User $user, int $targetDepartamentoId): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);

        return in_array($targetDepartamentoId, $userDeps);
    }

    /**
     * Departamentos que têm o documento em mãos, para efeitos de competência.
     *
     * `departamento_id` é a custódia formal e só muda no recebimento (ver
     * DocumentoEntradaService::receiveDocument). Enquanto um encaminhamento
     * espera recibo, quem tem o documento à frente é o destino — e é ele que o
     * tem de tratar.
     *
     * Reconhecer só a custódia formal fechava o ciclo sobre si mesmo: delegar
     * vale como recibo (DocumentoEntradaService::receberPendenteAoDelegar), mas
     * a guarda exigia o recibo para deixar delegar, e o chefe do departamento
     * de destino nunca conseguia agir sobre um documento que lhe fora
     * despachado.
     *
     * @return int[]
     */
    public function departamentosComCustodia($documento): array
    {
        $deps = [];

        if ($documento->departamento_id) {
            $deps[] = (int) $documento->departamento_id;
        }

        // Quem já tem os encaminhamentos carregados (a gaveta, a ficha) não paga
        // uma consulta por cada verificação: canConcluirTarefa e
        // canCancelarTarefa correm uma vez por tarefa na mesma página.
        $pendentes = $documento->relationLoaded('encaminhamentos')
            ? $documento->encaminhamentos
                ->filter(fn ($e) => $e->recebido_em === null)
                ->map(fn ($e) => (int) $e->destino_departamento_id)
                ->all()
            : DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->whereNull('recebido_em')
                ->pluck('destino_departamento_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        return array_values(array_unique(array_merge($deps, $pendentes)));
    }

    /**
     * Protocolo de entrada (recibo, etiqueta, comprovativo): só o Expediente
     * (departamentos com is_area_expediente) e o Secretário — responsável ou
     * super-chefe do gabinete a que pertence um departamento de expediente
     * (a Secretaria Geral) —, além do admin. Decisão do cliente, 2026-09-29.
     */
    public function podeVerProtocolo(User $user): bool
    {
        if ($user->isAdmin() || $this->isUserInAreaExpediente($user)) {
            return true;
        }

        return Gabinete::whereHas('departamentos', fn ($q) => $q->where('is_area_expediente', true))
            ->where(fn ($q) => $q->where('responsavel_id', $user->id)->orWhere('super_chefe_id', $user->id))
            ->exists();
    }

    /**
     * Regra ÚNICA de quem pode arquivar (entradas e internos); as policies
     * delegam aqui.
     *
     * Decisão provisória do cliente (2026-09-29): "por enquanto" arquivar fica
     * a cargo dos técnicos do departamento/gabinete que tem o documento à sua
     * guarda. Chefes de departamento, chefes de gabinete e quem registou deixam
     * de arquivar. Para alargar a outro perfil, acrescentar a condição aqui.
     */
    public function podeArquivar(User $user, DocumentoEntrada|DocumentoInterno $documento): bool
    {
        if ($user->isAdmin() || $this->isAdmin($user)) {
            return true;
        }

        if (! $user->isTecnico() || $this->isChefeDepartamento($user)) {
            return false;
        }

        $meus = $this->getUserDepartments($user);
        if ($meus === []) {
            return false;
        }

        if ($documento instanceof DocumentoEntrada) {
            return array_intersect($meus, $this->departamentosComCustodia($documento)) !== [];
        }

        if ($documento->departamento_id) {
            return in_array((int) $documento->departamento_id, $meus, true);
        }

        // Interno emitido pelo gabinete (sem departamento): guarda do gabinete,
        // logo dos técnicos dos seus departamentos.
        $gabineteId = $documento->gabineteEmissorId();

        return $gabineteId !== null
            && Departamento::whereIn('id', $meus)->where('gabinete_id', $gabineteId)->exists();
    }

    /**
     * Check if user has permission to manage tasks for a document.
     */
    public function canManageTasks(User $user, $documento): bool
    {
        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;

        // 0. Super Cabinet Chief
        if ($docGabId && $user->isSuperChefeDoGabinete($docGabId)) {
            return true;
        }

        // 1. Cabinet Responsible
        $userGabinetes = $this->getUserResponsibleGabinetes($user);
        if ($docGabId && in_array($docGabId, $userGabinetes)) {
            return true;
        }

        // 2. Administrador: desbloqueia qualquer documento (decisão de 2026-09-30).
        if ($user->isAdmin() || $this->isAdmin($user)) {
            return true;
        }

        // 3. Chefe DO departamento que tem o documento em mãos. Ter o papel e ser
        // membro não chega: um chefe de A que seja membro secundário de B, ou um
        // adjunto com o mesmo papel, não delega em B.
        return array_intersect($this->departamentosComCustodia($documento), $this->departamentosChefiados($user)) !== [];
    }

    /**
     * Departamentos de que o utilizador é o chefe designado — regra única de
     * "é chefe do departamento X" para delegar tarefas.
     *
     * Usa Departamento::chefeDesignado(): o responsável (responsavel_id, espelho
     * mantido pela DepartamentoChefiaService) ou, sem ele, o único utilizador do
     * departamento com o papel de chefe. Com dois ou mais candidatos não há chefe
     * — nunca se escolhe "um qualquer".
     *
     * @return int[]
     */
    public function departamentosChefiados(User $user): array
    {
        $candidatos = Departamento::where('responsavel_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($user->departamento_id && $this->isChefeDepartamento($user)) {
            $candidatos[] = (int) $user->departamento_id;
        }

        return Departamento::whereIn('id', array_unique($candidatos))->get()
            ->filter(fn (Departamento $dep) => (int) optional($dep->chefeDesignado())->id === (int) $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Chefia de um documento interno — regra única para editar em análise,
     * aprovar e devolver: o chefe designado do departamento emissor ou o
     * responsável do gabinete emissor (inclui os documentos emitidos pelo
     * próprio gabinete). Ter o papel de chefe não chega: um chefe de A que seja
     * membro secundário de B não manda nos documentos de B.
     */
    public function chefiaDoDocumentoInterno(User $user, DocumentoInterno $doc): bool
    {
        if ($doc->departamento_id && in_array((int) $doc->departamento_id, $this->departamentosChefiados($user), true)) {
            return true;
        }

        $gabinete = $doc->gabineteEmissor();

        return $gabinete !== null && (int) $gabinete->responsavel_id === (int) $user->id;
    }

    /**
     * Quem a chefia de um documento interno deve ser avisado quando ele entra
     * em análise: o chefe designado do departamento emissor ou, num documento
     * emitido pelo gabinete (ou de departamento sem chefe), o responsável do gabinete.
     */
    public function chefiaANotificar(DocumentoInterno $doc): ?User
    {
        $chefe = $doc->departamento?->chefeDesignado();
        if ($chefe) {
            return $chefe;
        }

        $responsavelId = $doc->gabineteEmissor()?->responsavel_id;

        return $responsavelId ? User::find($responsavelId) : null;
    }

    /**
     * Quem pode concluir uma tarefa: o utilizador a quem foi atribuída, quem
     * gere tarefas no documento, ou qualquer membro do departamento a que a
     * tarefa foi atribuída.
     *
     * Fonte única partilhada pelo endpoint e pela view — existiam três versões
     * desta regra (endpoint concluir, endpoint cancelar e show.blade.php) e
     * tinham divergido.
     */
    public function canConcluirTarefa(User $user, $documento, \App\Models\DocumentoTarefa $tarefa): bool
    {
        if ($tarefa->assigned_to_user_id && (int) $tarefa->assigned_to_user_id === (int) $user->id) {
            return true;
        }

        if ($this->canManageTasks($user, $documento)) {
            return true;
        }

        if ($tarefa->assigned_to_departamento_id) {
            return in_array((int) $tarefa->assigned_to_departamento_id, $this->getUserDepartments($user), true);
        }

        return false;
    }

    /**
     * Assumir uma tarefa em concorrência: só o técnico a quem a linha foi
     * oferecida, e só enquanto ninguém a assumiu. Se outro foi mais rápido, o
     * TarefaConcorrenciaService recusa com a mensagem — aqui decide-se o botão.
     */
    public function canAssumirTarefa(User $user, \App\Models\DocumentoTarefa $tarefa): bool
    {
        return $tarefa->aguardaQuemAssuma()
            && (int) $tarefa->assigned_to_user_id === (int) $user->id;
    }

    /**
     * Devolver ao grupo uma tarefa em concorrência já assumida e por concluir:
     * quem gere tarefas no documento (a chefia), por exemplo se o técnico se
     * ausentou.
     */
    public function canLibertarTarefa(User $user, $documento, \App\Models\DocumentoTarefa $tarefa): bool
    {
        return $tarefa->emConcorrencia()
            && $tarefa->status === 'pendente'
            && $tarefa->assumida_em !== null
            && $this->canManageTasks($user, $documento);
    }

    /**
     * Quem pode cancelar uma tarefa: quem a atribuiu ou quem gere tarefas no
     * documento.
     */
    public function canCancelarTarefa(User $user, $documento, \App\Models\DocumentoTarefa $tarefa): bool
    {
        if ((int) $tarefa->assigned_by_id === (int) $user->id) {
            return true;
        }

        return $this->canManageTasks($user, $documento);
    }

    /**
     * Check if the user is responsible for a specific cabinet.
     */
    public function isGabineteResponsavel(User $user, ?int $gabineteId): bool
    {
        if (! $gabineteId) {
            return false;
        }
        $responsibleGabinetes = $this->getUserResponsibleGabinetes($user);

        return in_array($gabineteId, $responsibleGabinetes);
    }

    /**
     * Check if the user has permission to view/download the document.
     */
    public function canViewDocument(User $user, DocumentoEntrada $documento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // 0. Criador do documento
        if ((int) $documento->user_id === (int) $user->id) {
            return true;
        }

        // 1. Super Chefe do Gabinete
        $docGabId = $documento->departamento ? (int) $documento->departamento->gabinete_id : null;
        if ($docGabId && method_exists($user, 'isSuperChefeDoGabinete') && $user->isSuperChefeDoGabinete($docGabId)) {
            return true;
        }

        $userDeps = $this->getUserDepartments($user);

        // 2. Departamento Atual do documento
        if (in_array((int) $documento->departamento_id, $userDeps)) {
            return true;
        }

        // 3. Responsável pelo Gabinete
        if ($docGabId && $this->isGabineteResponsavel($user, $docGabId)) {
            return true;
        }

        // 4. Departamentos de Destino (Despacho / Encaminhamento Múltiplo)
        if (count($userDeps) && $documento->departamentosDestino()->whereIn('departamentos.id', $userDeps)->exists()) {
            return true;
        }

        // 5. Histórico de Encaminhamentos
        if (count($userDeps)) {
            $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->where(function ($q) use ($userDeps) {
                    $q->whereIn('origem_departamento_id', $userDeps)
                        ->orWhereIn('destino_departamento_id', $userDeps);
                })->exists();

            if ($hasHistory) {
                return true;
            }
        }

        // 6. Possui Tarefa/Despacho atribuído ao utilizador ou ao seu departamento
        $hasTask = \App\Models\DocumentoTarefa::where('documento_entrada_id', $documento->id)
            ->where(function ($q) use ($user, $userDeps) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhere('assigned_by_id', $user->id)
                  ->orWhere('responsavel_user_id', $user->id);
                if (count($userDeps)) {
                    $q->orWhereIn('assigned_to_departamento_id', $userDeps);
                }
            })->exists();

        if ($hasTask) {
            return true;
        }

        return false;
    }
}
