<?php

namespace App\Http\Controllers;

use App\Enums\NivelColaboracao;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Notifications\ConviteColaboracaoNotification;
use App\Services\DocumentoCollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Endpoints da edição colaborativa em tempo real de Documentos Internos.
 * Todas as rotas são protegidas por sessão (`auth`) e pela feature flag `feature_collab`.
 */
class DocumentoColaboracaoController extends Controller
{
    public function __construct(private DocumentoCollaborationService $service) {}

    /**
     * Página do editor colaborativo (Tiptap + Yjs).
     */
    public function editor(DocumentoInterno $documentoInterno)
    {
        $this->authorize('collaborate', $documentoInterno);

        $documentoInterno->load(['especie', 'departamento.gabinete', 'autor']);

        $colaboradores = $documentoInterno->colaboradores()->with('user')->get();

        // Candidatos a convite: utilizadores do mesmo gabinete do documento (excluindo já colaboradores/autor).
        $gabineteId = optional($documentoInterno->departamento)->gabinete_id;
        $jaColaboram = $colaboradores->pluck('user_id')->push($documentoInterno->criado_por)->all();
        $candidatos = collect();
        if ($gabineteId) {
            $candidatos = User::whereHas('departamento', function ($q) use ($gabineteId) {
                $q->where('gabinete_id', $gabineteId);
            })->whereNotIn('id', $jaColaboram)->orderBy('name')->get(['id', 'name']);
        }

        $nivel = $this->service->nivelDe(Auth::user(), $documentoInterno);

        return view('documentos_internos.colaborar', [
            'documentoInterno' => $documentoInterno,
            'colaboradores' => $colaboradores,
            'candidatos' => $candidatos,
            'nivelAtual' => $nivel,
            'podeEditar' => $nivel !== null && $nivel->podeEditar(),
            'podeAdministrar' => $nivel !== null && $nivel->podeAdministrar(),
            'cor' => $this->service->corDoUtilizador(Auth::id()),
            'niveis' => NivelColaboracao::cases(),
        ]);
    }

    /**
     * Estado inicial Yjs (updates base64) + HTML de fallback.
     */
    public function state(DocumentoInterno $documentoInterno): JsonResponse
    {
        $this->authorize('collaborate', $documentoInterno);

        return response()->json($this->service->estadoInicial($documentoInterno));
    }

    /**
     * Recebe um delta Yjs para persistência durável e, opcionalmente, faz autosave do HTML.
     * (O relay de baixa latência entre pares é feito por WebSocket; isto é a camada de durabilidade.)
     */
    public function sync(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        abort_unless($this->service->podeEditar(Auth::user(), $documentoInterno), 403);

        $validated = $request->validate([
            'update' => 'required|string',
            'html' => 'nullable|string',
        ]);

        $this->service->registarUpdate($documentoInterno, Auth::user(), $validated['update']);

        if (! empty($validated['html'])) {
            $this->service->autosave($documentoInterno, $validated['html']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Checkpoint manual: cria uma versão no histórico e compacta o log Yjs.
     */
    public function checkpoint(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        abort_unless($this->service->podeEditar(Auth::user(), $documentoInterno), 403);

        $validated = $request->validate([
            'html' => 'required|string',
            'titulo' => 'nullable|string|max:255',
            'change_type' => 'nullable|in:patch,minor,major',
            'change_log' => 'nullable|string',
            'snapshot' => 'nullable|string',
        ]);

        $doc = $this->service->checkpoint(
            $documentoInterno,
            Auth::user(),
            $validated['html'],
            $validated['change_type'] ?? 'minor',
            $validated['change_log'] ?? null,
            $validated['snapshot'] ?? null,
            $validated['titulo'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'versao' => $doc->versao_semantica,
            'versao_atual' => $doc->versao_atual,
        ]);
    }

    /**
     * Guarda o título/assunto (autosave, sem criar versão).
     */
    public function salvarTitulo(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        abort_unless($this->service->podeEditar(Auth::user(), $documentoInterno), 403);

        $validated = $request->validate(['titulo' => 'required|string|max:255']);

        $this->service->salvarTitulo($documentoInterno, $validated['titulo']);

        return response()->json(['ok' => true]);
    }

    public function colaboradores(DocumentoInterno $documentoInterno): JsonResponse
    {
        $this->authorize('collaborate', $documentoInterno);

        $lista = $documentoInterno->colaboradores()->with('user:id,name')->get()
            ->map(fn ($c) => [
                'user_id' => $c->user_id,
                'nome' => $c->user?->name,
                'nivel' => $c->nivel->value,
            ]);

        return response()->json(['colaboradores' => $lista]);
    }

    public function convidar(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $this->authorize('manageCollaborators', $documentoInterno);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'nivel' => 'required|in:'.implode(',', NivelColaboracao::values()),
        ]);

        $invitee = User::findOrFail($validated['user_id']);

        try {
            $colab = $this->service->convidar(
                $documentoInterno,
                Auth::user(),
                $invitee,
                NivelColaboracao::from($validated['nivel'])
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $invitee->notify(new ConviteColaboracaoNotification($documentoInterno, Auth::user(), $colab->nivel));

        return response()->json([
            'ok' => true,
            'colaborador' => [
                'user_id' => $invitee->id,
                'nome' => $invitee->name,
                'nivel' => $colab->nivel->value,
            ],
        ], 201);
    }

    public function atualizarColaborador(Request $request, DocumentoInterno $documentoInterno, User $user): JsonResponse
    {
        $this->authorize('manageCollaborators', $documentoInterno);

        $validated = $request->validate([
            'nivel' => 'required|in:'.implode(',', NivelColaboracao::values()),
        ]);

        $this->service->definirNivel($documentoInterno, $user, NivelColaboracao::from($validated['nivel']));

        return response()->json(['ok' => true]);
    }

    public function removerColaborador(DocumentoInterno $documentoInterno, User $user): JsonResponse
    {
        $this->authorize('manageCollaborators', $documentoInterno);

        $this->service->remover($documentoInterno, $user);

        return response()->json(['ok' => true]);
    }
}
