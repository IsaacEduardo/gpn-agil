<?php

namespace App\Services;

use App\Exceptions\TarefaIndisponivelException;
use App\Models\AuditLog;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Tarefas delegadas em concorrência: a mesma tarefa é oferecida a vários
 * técnicos (uma linha por técnico, mesmo grupo_tarefa_uuid) e o primeiro a
 * assumi-la fica com ela; as dos colegas passam a 'retirada'.
 *
 * Mantém-se uma linha por técnico para que tudo o que já lê "tarefas pendentes
 * do utilizador" (Atribuídos a Mim, painel, Minhas Tarefas) e o fecho do
 * documento (sem tarefas pendentes => tratado) continuem a funcionar sem saber
 * que a concorrência existe.
 *
 * Toda a decisão de quem fica com a tarefa passa por assumir(), com as linhas
 * do grupo bloqueadas: dois cliques simultâneos não podem dar dois donos.
 */
class TarefaConcorrenciaService
{
    /**
     * Dá a tarefa ao técnico a quem a linha pertence e retira-a aos colegas.
     *
     * Idempotente para o próprio dono. A autorização (quem pode pedir isto) é do
     * chamador; aqui decide-se apenas se a tarefa ainda está disponível.
     *
     * @throws TarefaIndisponivelException se outro técnico a assumiu entretanto,
     *                                     ou se a linha já não está pendente.
     */
    public function assumir(DocumentoTarefa $tarefa, User $actor): DocumentoTarefa
    {
        if (! $tarefa->emConcorrencia()) {
            return $tarefa;
        }

        [$propria, $retiradas] = DB::transaction(function () use ($tarefa) {
            $grupo = $this->bloquearGrupo($tarefa);
            $propria = $grupo->firstWhere('id', $tarefa->id);
            $dono = $this->donoDoGrupo($grupo);

            if ($dono && $dono->id === $propria->id) {
                return [$propria, collect()];
            }
            if ($dono) {
                $nome = optional(User::find($dono->assigned_to_user_id))->name ?? 'outro técnico';

                throw new TarefaIndisponivelException("Esta tarefa já foi assumida por {$nome}.");
            }
            if ($propria->status !== 'pendente') {
                throw new TarefaIndisponivelException('Esta tarefa já não está disponível.');
            }

            $propria->assumida_em = now();
            $propria->save();

            $retiradas = $grupo->filter(fn (DocumentoTarefa $t) => $t->id !== $propria->id && $t->status === 'pendente')
                ->each(function (DocumentoTarefa $t) {
                    $t->status = DocumentoTarefa::STATUS_RETIRADA;
                    $t->save();
                })->values();

            return [$propria, $retiradas];
        });

        if ($retiradas->isNotEmpty()) {
            $tecnico = optional(User::find($propria->assigned_to_user_id))->name ?? 'técnico';

            $this->audit('tarefa.assumida', $propria, $actor, [
                'tarefa_id' => $propria->id,
                'assumida_por' => $propria->assigned_to_user_id,
                'retiradas' => $retiradas->pluck('id')->all(),
            ]);

            $this->notificar(
                $retiradas->pluck('assigned_to_user_id')->push($propria->assigned_by_id)->all(),
                $propria,
                'Tarefa assumida: '.$propria->titulo,
                "Assumida por {$tecnico}.",
                'tarefa_assumida',
                $actor
            );
        }

        return $propria;
    }

    /**
     * Devolve ao grupo uma tarefa assumida e ainda por concluir: volta a estar
     * disponível para todos os técnicos a quem foi oferecida.
     *
     * @throws TarefaIndisponivelException se a tarefa não está assumida e pendente.
     */
    public function libertar(DocumentoTarefa $tarefa, User $actor): DocumentoTarefa
    {
        if (! $tarefa->emConcorrencia()) {
            throw new TarefaIndisponivelException('Só uma tarefa em concorrência pode ser devolvida ao grupo.');
        }

        [$propria, $devolvidas] = DB::transaction(function () use ($tarefa) {
            $grupo = $this->bloquearGrupo($tarefa);
            $propria = $grupo->firstWhere('id', $tarefa->id);

            if ($propria->status !== 'pendente' || $propria->assumida_em === null) {
                throw new TarefaIndisponivelException('Só se devolve ao grupo uma tarefa assumida e ainda por concluir.');
            }

            $propria->assumida_em = null;
            $propria->save();

            $devolvidas = $grupo->filter(fn (DocumentoTarefa $t) => $t->status === DocumentoTarefa::STATUS_RETIRADA)
                ->each(function (DocumentoTarefa $t) {
                    $t->status = 'pendente';
                    $t->save();
                })->values();

            return [$propria, $devolvidas];
        });

        $this->audit('tarefa.libertada', $propria, $actor, [
            'tarefa_id' => $propria->id,
            'estava_com' => $tarefa->assigned_to_user_id,
            'devolvidas' => $devolvidas->pluck('id')->all(),
        ]);

        $this->notificar(
            $devolvidas->pluck('assigned_to_user_id')->push($propria->assigned_to_user_id)->all(),
            $propria,
            'Tarefa de novo disponível: '.$propria->titulo,
            'A chefia devolveu-a ao grupo: o primeiro a assumir fica com ela.',
            'tarefa_libertada',
            $actor
        );

        return $propria;
    }

    /**
     * Cancelar uma tarefa de um grupo que ainda espera quem a assuma cancela o
     * grupo inteiro: as linhas são a mesma oferta, não tarefas distintas.
     *
     * @return int quantas linhas do grupo, além desta, foram canceladas.
     */
    public function cancelarRestantesDoGrupo(DocumentoTarefa $tarefa): int
    {
        if (! $tarefa->emConcorrencia()) {
            return 0;
        }

        return DocumentoTarefa::doGrupo($tarefa->grupo_tarefa_uuid)
            ->where('id', '!=', $tarefa->id)
            ->whereIn('status', ['pendente', DocumentoTarefa::STATUS_RETIRADA])
            ->update(['status' => 'cancelada', 'updated_at' => now()]);
    }

    /** @return Collection<int, DocumentoTarefa> */
    private function bloquearGrupo(DocumentoTarefa $tarefa): Collection
    {
        return DocumentoTarefa::doGrupo($tarefa->grupo_tarefa_uuid)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** A linha que ficou com a tarefa: assumida e não cancelada. */
    private function donoDoGrupo(Collection $grupo): ?DocumentoTarefa
    {
        return $grupo->first(fn (DocumentoTarefa $t) => $t->assumida_em !== null && in_array($t->status, ['pendente', 'concluida'], true));
    }

    private function notificar(array $userIds, DocumentoTarefa $tarefa, string $titulo, string $corpo, string $tipo, User $actor): void
    {
        $destinatarios = User::whereIn('id', array_filter(array_unique($userIds)))
            ->where('id', '!=', $actor->id)
            ->get();

        if ($destinatarios->isEmpty()) {
            return;
        }

        $documento = $tarefa->documento;
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);

        Notification::send($destinatarios, new SimpleBroadcastNotification(
            $titulo,
            "Documento {$numero}. {$corpo}",
            route('documentos-entradas.show', $documento->id),
            'normal',
            $tipo
        ));
    }

    /** Mesmo registo de auditoria do DocumentoEntradaService; nunca bloqueia a operação. */
    private function audit(string $action, DocumentoTarefa $tarefa, User $actor, array $newValues): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $action,
                'auditable_type' => DocumentoEntrada::class,
                'auditable_id' => $tarefa->documento_entrada_id,
                'old_values' => null,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Auditoria nunca deve impedir a operação.
        }
    }
}
