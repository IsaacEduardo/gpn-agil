<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Encaminhamento de documentos de entrada (individual, em lote, receção,
 * cancelamento e saída de gabinete). Extraído de DocumentoEntradaController.
 */
class DocumentoEntradaEncaminhamentoController extends Controller
{
    public function __construct(
        protected DocumentoEntradaService $documentoService,
        protected DocumentoPermissionService $permissionService,
    ) {}

    public function encaminhar(Request $request, DocumentoEntrada $documento)
    {
        $this->authorize('encaminhar', $documento);

        $validated = $request->validate([
            'destino_departamento_id' => ['required', 'exists:departamentos,id'],
            'observacao' => ['nullable', 'string'],
        ]);

        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return $this->respondForwardError($request, 'destino_departamento_id', 'Há encaminhamento pendente; aguarde o recebimento antes de criar um novo.');
        }
        if ($documento->departamento_id === (int) $validated['destino_departamento_id']) {
            return $this->respondForwardError($request, 'destino_departamento_id', 'Selecione um departamento diferente do atual.');
        }

        try {
            $enc = $this->documentoService->forwardDocument(
                $documento,
                (int) $validated['destino_departamento_id'],
                $validated['observacao'] ?? null,
                Auth::user()
            );
        } catch (\RuntimeException $e) {
            // Corrida fechada pelo lock atómico do serviço.
            return $this->respondForwardError($request, 'destino_departamento_id', $e->getMessage());
        }

        if ($request->wantsJson()) {
            $destino = Departamento::find((int) $validated['destino_departamento_id']);

            return response()->json([
                'success' => true,
                'message' => 'Documento encaminhado com sucesso.',
                'documento_id' => $documento->id,
                'encaminhamento_id' => $enc->id,
                'destino' => $destino?->nome,
                'encaminhado_em' => optional($enc->encaminhado_em)->format('d/m'),
            ]);
        }

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Documento encaminhado com sucesso.');
    }

    /**
     * Encaminhamento em lote para um único departamento de destino.
     * Autorização e validações são feitas por documento dentro do serviço.
     */
    public function batchEncaminhar(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'json'],
            'destino_departamento_id' => ['required', 'exists:departamentos,id'],
            'observacao' => ['nullable', 'string'],
        ]);

        $ids = json_decode($validated['ids'], true);
        if (! is_array($ids) || empty($ids)) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Nenhum documento selecionado.'], 422)
                : back()->with('error', 'Nenhum documento selecionado.');
        }

        $result = $this->documentoService->forwardBatch(
            array_map('intval', $ids),
            (int) $validated['destino_departamento_id'],
            $validated['observacao'] ?? null,
            Auth::user()
        );

        $message = $result['success'] > 0
            ? $result['success'].' documento(s) encaminhado(s) com sucesso.'.($result['failed'] > 0 ? ' ('.$result['failed'].' falharam, sem permissão, pendentes ou já no destino).' : '')
            : 'Nenhum documento pôde ser encaminhado. Verifique permissões, pendências ou o departamento de destino.';

        if ($request->wantsJson()) {
            return response()->json(['success' => $result['success'] > 0, 'message' => $message] + $result, $result['success'] > 0 ? 200 : 422);
        }

        return $result['success'] > 0 ? back()->with('success', $message) : back()->with('error', $message);
    }

    public function batchReceber(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'json'],
        ]);

        $ids = json_decode($validated['ids'], true);
        if (! is_array($ids) || empty($ids)) {
            return back()->with('error', 'Nenhum documento selecionado.');
        }

        $result = $this->documentoService->receiveBatch($ids, Auth::user());

        if ($result['success'] > 0) {
            $msg = $result['success'].' documento(s) recebido(s) com sucesso.';
            if ($result['failed'] > 0) {
                $msg .= ' ('.$result['failed'].' falharam ou sem permissão).';
            }

            return back()->with('success', $msg);
        }

        return back()->with('error', 'Não foi possível receber os documentos selecionados. Verifique as permissões.');
    }

    public function cancelar(DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento)
    {
        // 1. Validações básicas
        if ((int) $encaminhamento->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }

        // 2. Verifica se já foi recebido (não pode cancelar se o destino já recebeu)
        if ($encaminhamento->recebido_em) {
            return back()->with('error', 'Não é possível cancelar. O documento já foi recebido pelo destino.');
        }

        $actor = Auth::user();

        // 3. Autor, chefia do departamento de origem ou responsável do gabinete.
        //    Ver DocumentoEntradaPolicy::cancelarEncaminhamento.
        if (! Gate::forUser($actor)->allows('cancelarEncaminhamento', [$documento, $encaminhamento])) {
            abort(403, 'Você não tem permissão para cancelar este encaminhamento.');
        }

        // 4. Executa o cancelamento via Service
        $this->documentoService->cancelForwarding($encaminhamento, $actor);

        return back()->with('success', 'Encaminhamento cancelado com sucesso. O documento está disponível novamente.');
    }

    public function receber(Request $request, DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento)
    {
        if ((int) $encaminhamento->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }

        $actor = Auth::user();
        if (! $this->permissionService->canReceiveInDepartment($actor, (int) $encaminhamento->destino_departamento_id)) {
            abort(403);
        }

        // Idempotente: receiveDocument devolve false se já estava recebido (corrida).
        $recebeu = $encaminhamento->recebido_em
            ? false
            : $this->documentoService->receiveDocument($documento, $encaminhamento, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'already' => ! $recebeu,
                'message' => $recebeu ? 'Documento marcado como recebido.' : 'Encaminhamento já marcado como recebido.',
                'documento_id' => $documento->id,
            ]);
        }

        return $recebeu
            ? back()->with('success', 'Documento marcado como recebido.')
            : back()->with('info', 'Encaminhamento já marcado como recebido.');
    }

    public function saidaGabinete(Request $request, DocumentoEntrada $documento)
    {
        $this->authorize('saidaGabinete', $documento);

        $validated = $request->validate([
            'destino_gabinete_id' => ['required', 'exists:gabinetes,id'],
            'saida_gabinete_data' => ['required', 'date'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
        ]);

        $actor = Auth::user();

        $docGabineteId = optional($documento->departamento)->gabinete_id;
        if ($documento->saida_gabinete_data) {
            return back()->withErrors(['destino_gabinete_id' => 'Documento já possui saída de gabinete registrada.']);
        }
        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return back()->withErrors(['destino_gabinete_id' => 'Há encaminhamento interno pendente; receba antes de dar saída.']);
        }
        if ($docGabineteId && (int) $validated['destino_gabinete_id'] === (int) $docGabineteId) {
            return back()->withErrors(['destino_gabinete_id' => 'Selecione um gabinete diferente do atual.']);
        }

        $this->documentoService->sendToGabinete(
            $documento,
            (int) $validated['destino_gabinete_id'],
            $validated['saida_gabinete_data'],
            $validated['encaminhamento_oficio_numero'] ?? null,
            $request->input('observacao'),
            $actor
        );

        return redirect()->route('documentos-entradas.show', $documento)
            ->with('success', 'Saída do gabinete registrada com sucesso.');
    }

    /**
     * Resposta de erro do encaminhamento: JSON 422 para pedidos AJAX, redirect com
     * erros de validação para pedidos web (mantém o comportamento original).
     */
    private function respondForwardError(Request $request, string $field, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message]);
    }
}
