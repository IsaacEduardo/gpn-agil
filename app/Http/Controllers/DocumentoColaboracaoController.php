<?php

namespace App\Http\Controllers;

use App\Enums\NivelColaboracao;
use App\Events\EventoColaborativo;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Notifications\ConviteColaboracaoNotification;
use App\Services\ActividadeColaborativaService;
use App\Services\DocumentoCollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints da edição colaborativa em tempo real de Documentos Internos.
 * Todas as rotas são protegidas por sessão (`auth`) e pela feature flag `feature_collab`.
 */
class DocumentoColaboracaoController extends Controller
{
    /**
     * true desde que o schema Tiptap (resources/js/collab/extensoes.js) preserva os
     * marcadores estruturados — verificado com um teste de ida e volta sobre um ofício real.
     * Voltar a false bloqueia de imediato a colaboração nesses documentos. A guarda do
     * servidor em sync/checkpoint mantém-se em qualquer caso.
     */
    public const EDITOR_PRESERVA_ESTRUTURA = true;

    public function __construct(
        private DocumentoCollaborationService $service,
        private ActividadeColaborativaService $actividade,
    ) {}

    /**
     * Página do editor colaborativo (Tiptap + Yjs).
     */
    public function editor(DocumentoInterno $documentoInterno)
    {
        $this->authorize('collaborate', $documentoInterno);

        // Temporário: o editor colaborativo ainda não preserva os marcadores dos modelos
        // estruturados (ofício). Até preservar, estes documentos usam o editor clássico.
        if (! self::EDITOR_PRESERVA_ESTRUTURA && $documentoInterno->temConteudoEstruturado()) {
            return redirect()->route('documentos-internos.edit', $documentoInterno)
                ->with('warning', 'Este documento foi gerado a partir de um modelo estruturado e, por agora, só pode ser editado no editor clássico.');
        }

        $documentoInterno->load(['especie', 'departamento.gabinete', 'gabinete', 'autor']);

        $colaboradores = $documentoInterno->colaboradores()->with('user')->get();

        // Candidatos a convite: utilizadores do mesmo gabinete do documento (excluindo já colaboradores/autor).
        $gabineteId = $documentoInterno->gabineteEmissorId();
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
            'podeComentar' => $nivel !== null && $nivel->podeComentar(),
            'versaoAtual' => $documentoInterno->versao_semantica,
            'porGuardar' => $this->service->temAlteracoesPorGuardar($documentoInterno),
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

        return response()->json($this->service->estadoInicial($documentoInterno, Auth::user()));
    }

    /**
     * Recebe um delta Yjs: grava-o no log e retransmite-o aos outros participantes
     * (o servidor é o único caminho das alterações, para o nível de quem escreve ser
     * verificado). Opcionalmente faz autosave do HTML.
     */
    public function sync(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $validated = $request->validate([
            'update' => 'nullable|string|required_without:html',
            'html' => 'nullable|string',
            'seed' => 'nullable|boolean',
            'revisao' => 'nullable|integer',
        ]);

        if ($recusa = $this->recusarGravacao($request, $documentoInterno)) {
            return $recusa;
        }

        // Seed: estado inicial de quem abriu primeiro, sem HTML (nada muda no documento).
        if ($request->boolean('seed')) {
            $linha = filled($validated['update'] ?? null)
                ? $this->service->registarSeed($documentoInterno, Auth::user(), $validated['update'])
                : null;
            if (! $linha) {
                return response()->json(['ok' => false, 'seed_rejeitado' => true], 409);
            }

            $this->service->retransmitir($documentoInterno, $linha);

            return response()->json(['ok' => true, 'id' => $linha->id]);
        }

        $id = null;
        if (filled($validated['update'] ?? null)) {
            $linha = $this->service->registarUpdate($documentoInterno, Auth::user(), $validated['update']);
            $this->service->retransmitir($documentoInterno, $linha);
            $this->service->registarEdicaoParaResumo($documentoInterno, Auth::user());
            $this->actividade->registarEdicao($documentoInterno, Auth::user());
            $id = $linha->id;
        }

        if (! empty($validated['html'])) {
            if ($this->service->degradaConteudo($documentoInterno, $validated['html'])) {
                Log::warning('Autosave colaborativo recusado: o HTML perderia marcadores estruturados.', [
                    'documento_interno_id' => $documentoInterno->id,
                    'user_id' => Auth::id(),
                ]);

                return response()->json(['ok' => true, 'id' => $id, 'html_rejeitado' => true]);
            }

            $this->service->autosave($documentoInterno, $validated['html']);
        }

        return response()->json([
            'ok' => true,
            'id' => $id,
            'compactar' => $id !== null && $this->service->precisaCompactar($documentoInterno),
        ]);
    }

    /**
     * Compactação automática (sem versão), pedida pelo servidor na resposta do /sync
     * quando o log passa de LIMITE_LOG linhas. Mesma regra do checkpoint: só se apagam
     * as linhas que o cliente diz já ter aplicado.
     */
    public function compactar(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $validated = $request->validate([
            'snapshot' => 'required|string',
            'ids_aplicados' => 'required|array|min:1',
            'ids_aplicados.*' => 'integer',
            'revisao' => 'nullable|integer',
        ]);

        if ($recusa = $this->recusarGravacao($request, $documentoInterno)) {
            return $recusa;
        }

        $id = $this->service->compactar($documentoInterno, Auth::user(), $validated['snapshot'], $validated['ids_aplicados']);

        return response()->json(['ok' => true, 'snapshot_id' => $id]);
    }

    /**
     * Updates do log com os ids, para o cliente apanhar o que lhe faltou (ligação em
     * tempo real perdida, ou um update grande demais para seguir na mensagem).
     */
    public function updates(DocumentoInterno $documentoInterno): JsonResponse
    {
        $this->authorize('collaborate', $documentoInterno);

        $estado = $this->service->estadoInicial($documentoInterno);

        return response()->json([
            'updates' => $estado['updates'],
            'ids' => $estado['ids'],
        ]);
    }

    /**
     * Checkpoint manual: cria uma versão no histórico e compacta o log Yjs.
     */
    public function checkpoint(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $validated = $request->validate([
            'html' => 'required|string',
            'titulo' => 'nullable|string|max:255',
            'change_type' => 'nullable|in:patch,minor,major',
            'change_log' => 'nullable|string',
            'snapshot' => 'nullable|string',
            'ids_aplicados' => 'nullable|array',
            'ids_aplicados.*' => 'integer',
            'revisao' => 'nullable|integer',
        ]);

        if ($recusa = $this->recusarGravacao($request, $documentoInterno)) {
            return $recusa;
        }

        if ($this->service->degradaConteudo($documentoInterno, $validated['html'])) {
            return response()->json([
                'message' => 'Não foi possível guardar a versão: o conteúdo perderia campos do modelo '
                    .'(Assunto, destinatário ou referência). Use o editor clássico para alterar esses campos.',
            ], 422);
        }

        $doc = $this->service->checkpoint(
            $documentoInterno,
            Auth::user(),
            $validated['html'],
            $validated['change_type'] ?? 'minor',
            $validated['change_log'] ?? null,
            $validated['snapshot'] ?? null,
            $validated['titulo'] ?? null,
            $validated['ids_aplicados'] ?? [],
        );

        if ($doc === null) {
            $versao = $documentoInterno->fresh()->versao_semantica;

            return response()->json([
                'ok' => false,
                'sem_alteracoes' => true,
                'versao' => $versao,
                'message' => "Não há alterações desde a v{$versao}: nenhuma versão nova foi criada.",
            ], 409);
        }

        $this->service->registarEdicaoParaResumo($doc, Auth::user());

        // Os colegas actualizam o número da versão e o estado do botão.
        $this->service->transmitir($doc, EventoColaborativo::VERSAO, [
            'versao' => $doc->versao_semantica,
            'user_id' => Auth::id(),
            'autor' => Auth::user()->name,
        ], excetoQuemEnviou: true);

        return response()->json([
            'ok' => true,
            'versao' => $doc->versao_semantica,
            'versao_atual' => $doc->versao_atual,
            'snapshot_id' => $doc->getAttribute('snapshot_id'),
        ]);
    }

    /**
     * Guarda o título/assunto (autosave, sem criar versão).
     */
    public function salvarTitulo(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'revisao' => 'nullable|integer',
        ]);

        if ($recusa = $this->recusarGravacao($request, $documentoInterno)) {
            return $recusa;
        }

        $this->service->salvarTitulo($documentoInterno, $validated['titulo']);
        $this->actividade->registarCampos($documentoInterno, Auth::user(), ActividadeColaborativaService::TITULO, ['titulo' => $validated['titulo']]);
        $this->service->registarEdicaoParaResumo($documentoInterno, Auth::user());

        return response()->json(['ok' => true]);
    }

    /**
     * Guarda os dados do destinatário (autosave, sem criar versão), como o título.
     * O texto no corpo é actualizado no editor e chega aos outros pelo Yjs.
     */
    public function salvarCampos(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        $validated = $request->validate([
            'destinatario_nome' => 'sometimes|nullable|string|max:255',
            'destinatario_cargo' => 'sometimes|nullable|string|max:255',
            'destinatario_orgao' => 'sometimes|nullable|string|max:255',
            'destinatario_local' => 'sometimes|nullable|string|max:255',
            'revisao' => 'nullable|integer',
        ]);

        if ($recusa = $this->recusarGravacao($request, $documentoInterno)) {
            return $recusa;
        }

        $this->service->salvarDestinatario($documentoInterno, $validated);
        $campos = array_intersect_key($validated, array_flip(['destinatario_nome', 'destinatario_cargo', 'destinatario_orgao', 'destinatario_local']));
        if ($campos !== []) {
            $this->actividade->registarCampos($documentoInterno, Auth::user(), ActividadeColaborativaService::DESTINATARIO, $campos);
            $this->service->registarEdicaoParaResumo($documentoInterno, Auth::user());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Regra única de gravação: nível Editar, documento em rascunho e não bloqueado
     * (DocumentoCollaborationService::motivoRecusaGravacao) e sessão aberta depois
     * da última gravação clássica. Devolve a resposta de recusa, ou null.
     */
    private function recusarGravacao(Request $request, DocumentoInterno $documentoInterno): ?JsonResponse
    {
        $motivo = $this->service->motivoRecusaGravacao(Auth::user(), $documentoInterno);

        if ($motivo === 'nivel') {
            return response()->json([
                'ok' => false,
                'sem_permissao' => true,
                'message' => 'Já não tem permissão para editar este documento.',
            ], 403);
        }

        if ($motivo === 'encerrada') {
            return response()->json([
                'ok' => false,
                'sessao_encerrada' => true,
                'message' => 'Este documento já não está em rascunho: a edição colaborativa terminou e nada mais é gravado.',
            ], 409);
        }

        // Sessão aberta antes de uma gravação clássica: o log Yjs em que se baseia foi
        // descartado, por isso nada do que envia pode ser aceite.
        if ($this->service->sessaoDesactualizada($documentoInterno, $request->filled('revisao') ? (int) $request->input('revisao') : null)) {
            return $this->respostaSessaoDesactualizada();
        }

        return null;
    }

    private function respostaSessaoDesactualizada(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'versao_desactualizada' => true,
            'message' => 'Este documento foi alterado no editor clássico. Recarregue a página para continuar.',
        ], 409);
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
        $this->actividade->registar($documentoInterno, Auth::user(), ActividadeColaborativaService::CONVITE, [
            'colaborador_id' => $invitee->id,
            'colaborador' => $invitee->name,
            'nivel' => $colab->nivel->value,
        ]);

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
        $this->actividade->registar($documentoInterno, Auth::user(), ActividadeColaborativaService::NIVEL, [
            'colaborador_id' => $user->id,
            'colaborador' => $user->name,
            'nivel' => $validated['nivel'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function removerColaborador(DocumentoInterno $documentoInterno, User $user): JsonResponse
    {
        $this->authorize('manageCollaborators', $documentoInterno);

        $this->service->remover($documentoInterno, $user);
        $this->actividade->registar($documentoInterno, Auth::user(), ActividadeColaborativaService::REMOCAO, [
            'colaborador_id' => $user->id,
            'colaborador' => $user->name,
        ]);

        return response()->json(['ok' => true]);
    }
}
