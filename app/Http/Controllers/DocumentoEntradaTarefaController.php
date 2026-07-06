<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tarefas dentro de um documento de entrada (designar, concluir, cancelar).
 * Extraído de DocumentoEntradaController.
 */
class DocumentoEntradaTarefaController extends Controller
{
    public function __construct(
        protected DocumentoEntradaService $documentoService,
        protected DocumentoPermissionService $permissionService,
    ) {}

    public function store(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'in:usuario,departamento'],
            'destino_id' => ['required_if:tipo,departamento', 'nullable', 'integer'],
            'destino_ids' => ['required_if:tipo,usuario', 'nullable', 'array'],
            'destino_ids.*' => ['integer', 'exists:users,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'prazo_at' => ['nullable', 'date'],
        ]);

        $actor = Auth::user();
        if (! $this->permissionService->canManageTasks($actor, $documento)) {
            return back()->with('danger', 'Você não tem permissão para designar tarefa neste documento.');
        }

        $tipo = $validated['tipo'];
        $docGabId = optional($documento->departamento)->gabinete_id;
        $isSuperChefe = $actor->isSuperChefeDoGabinete($docGabId);

        if ($tipo === 'usuario') {
            $destinoIds = $request->destino_ids ?? [];
            if (empty($destinoIds)) {
                return back()->withErrors(['destino_ids' => 'Selecione pelo menos um usuário de destino.']);
            }

            $usuariosValidos = [];
            foreach ($destinoIds as $userId) {
                $user = User::find($userId);
                if (! $user) {
                    return back()->withErrors(['destino_ids' => 'Usuário não encontrado.']);
                }

                if ($isSuperChefe) {
                    $gabinete = $documento->departamento ? $documento->departamento->gabinete : null;
                    $isChefeGab = $gabinete && (int) $gabinete->responsavel_id === (int) $user->id;
                    $isChefeDep = Departamento::where('gabinete_id', $docGabId)->where('responsavel_id', $user->id)->exists();

                    if (! $isChefeGab && ! $isChefeDep) {
                        return back()->withErrors(['destino_ids' => 'O Super Chefe só pode delegar tarefas ao Chefe de Gabinete ou aos Chefes de Departamento do respetivo gabinete.']);
                    }
                } else {
                    $isGabResp = $this->permissionService->isGabineteResponsavel($actor, $docGabId);

                    if ($isGabResp) {
                        $uDep = $user->departamento;
                        if (! $uDep || $uDep->gabinete_id !== $docGabId) {
                            return back()->withErrors(['destino_ids' => 'Selecione usuário do seu gabinete.']);
                        }
                    } else {
                        // Chief Dept
                        $depId = (int) $documento->departamento_id;
                        $belongs = ((int) $user->departamento_id === $depId) || $user->departamentos()->where('departamento_id', $depId)->exists();
                        if (! $belongs) {
                            return back()->withErrors(['destino_ids' => 'Selecione usuário do seu departamento.']);
                        }
                    }
                }
                $usuariosValidos[] = $user;
            }

            // Gerar UUID comum para agrupar as tarefas
            $grupoUuid = (count($usuariosValidos) > 1) ? (string) Str::uuid() : null;

            foreach ($usuariosValidos as $user) {
                $data = [
                    'titulo' => $validated['titulo'],
                    'descricao' => $validated['descricao'] ?? null,
                    'prazo_at' => $validated['prazo_at'] ?? null,
                    'assigned_to_user_id' => $user->id,
                    'grupo_tarefa_uuid' => $grupoUuid,
                ];
                $this->documentoService->createTask($documento, $data, $actor);
            }
        } else {
            // departamento
            $destinoId = (int) $validated['destino_id'];
            if ($isSuperChefe) {
                return back()->withErrors(['tipo' => 'O Super Chefe apenas pode delegar tarefas a utilizadores específicos.']);
            }

            $dep = Departamento::find($destinoId);
            if (! $dep) {
                return back()->withErrors(['destino_id' => 'Departamento não encontrado.']);
            }

            $isGabResp = $this->permissionService->isGabineteResponsavel($actor, $docGabId);

            if (! $isGabResp) {
                return back()->withErrors(['tipo' => 'Apenas responsável do gabinete pode designar ao departamento.']);
            }
            if ((int) $dep->gabinete_id !== (int) $docGabId) {
                return back()->withErrors(['destino_id' => 'Selecione departamento do seu gabinete.']);
            }

            $data = [
                'titulo' => $validated['titulo'],
                'descricao' => $validated['descricao'] ?? null,
                'prazo_at' => $validated['prazo_at'] ?? null,
                'assigned_to_departamento_id' => $dep->id,
            ];
            $this->documentoService->createTask($documento, $data, $actor);
        }

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Tarefa designada com sucesso.');
    }

    public function concluir(Request $request, DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            return back()->with('info', 'Tarefa já atualizada.');
        }

        $actor = Auth::user();

        $can = false;
        if ($tarefa->assigned_to_user_id && (int) $tarefa->assigned_to_user_id === (int) $actor->id) {
            $can = true;
        } elseif ($this->permissionService->canManageTasks($actor, $documento)) {
            $can = true;
        }

        // Specific case: task assigned to department, check if user is in that department
        if ($tarefa->assigned_to_departamento_id) {
            $userDeps = $this->permissionService->getUserDepartments($actor);
            if (in_array($tarefa->assigned_to_departamento_id, $userDeps)) {
                $can = true;
            }
        }

        if (! $can) {
            return back()->with('danger', 'Sem permissão para concluir esta tarefa.');
        }

        $responsavelId = null;
        if ($tarefa->assigned_to_departamento_id) {
            $respId = $request->input('responsavel_user_id');
            if ($respId) {
                // Validate responsible
                $user = User::find((int) $respId);
                $dep = Departamento::find($tarefa->assigned_to_departamento_id);
                $belongs = $user && (((int) $user->departamento_id === (int) $dep->id) || $user->departamentos()->where('departamento_id', $dep->id)->exists());
                if (! $belongs) {
                    return back()->withErrors(['responsavel_user_id' => 'Selecione um responsável pertencente ao departamento destino.']);
                }
                $responsavelId = (int) $respId;
            } else {
                $userDeps = $this->permissionService->getUserDepartments($actor);
                if (in_array($tarefa->assigned_to_departamento_id, $userDeps)) {
                    $responsavelId = $actor->id;
                } else {
                    return back()->withErrors(['responsavel_user_id' => 'Informe o responsável atual do departamento para concluir.']);
                }
            }
        }

        $this->documentoService->completeTask($tarefa, $actor, $responsavelId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tarefa marcada como concluída.']);
        }

        return back()->with('success', 'Tarefa marcada como concluída.');
    }

    public function cancelar(Request $request, DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            $msg = 'Tarefa já atualizada.';

            return $request->wantsJson() ? response()->json(['message' => $msg], 400) : back()->with('info', $msg);
        }

        $actor = Auth::user();
        $can = false;
        if ((int) $tarefa->assigned_by_id === (int) $actor->id) {
            $can = true;
        } elseif ($this->permissionService->canManageTasks($actor, $documento)) {
            $can = true;
        }

        if (! $can) {
            $msg = 'Sem permissão para cancelar esta tarefa.';

            return $request->wantsJson() ? response()->json(['message' => $msg], 403) : back()->with('danger', $msg);
        }

        $this->documentoService->cancelTask($tarefa, $actor);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tarefa cancelada com sucesso.']);
        }

        return back()->with('success', 'Tarefa cancelada com sucesso.');
    }
}
