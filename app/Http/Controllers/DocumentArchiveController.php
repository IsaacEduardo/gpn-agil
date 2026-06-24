<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveDocumentRequest;
use App\Jobs\ArchiveDocumentJob;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\Pasta;
use App\Services\PastaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Arquivamento de documentos por arrastar-e-soltar (entrada e interno), em lote.
 *
 * Estanqueidade da autorização (defesa em profundidade):
 *  1. Form Request: o utilizador tem de estar autenticado e o payload é validado.
 *  2. Pré-validação da pasta de destino (acessível ao utilizador + compatível com o
 *     tipo) ANTES de despachar — devolve 403/422 imediato para feedback síncrono.
 *  3. Autorização por documento via Policy 'archive' (cada documento pode ter
 *     dono/departamento diferente) — itens negados são reportados individualmente.
 *  4. O ArchiveService volta a validar a pasta dentro do Job (o cliente nunca é
 *     a fonte de verdade).
 *
 * O trabalho pesado corre de forma assíncrona via ArchiveDocumentJob.
 */
class DocumentArchiveController extends Controller
{
    public function __construct(private PastaService $pastaService) {}

    /**
     * Arquivar múltiplos documentos (lote via drag-and-drop).
     *
     * Resposta:
     *  - 200 com { dispatched: [...ids], denied: [{id, reason}] } quando há ≥1 despachado;
     *  - 403/422 (pré-validação da pasta) ou 422 (nenhum despachado) caso contrário.
     */
    public function store(ArchiveDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('document_type'); // entrada | interno
        $modelClass = $type === 'interno' ? DocumentoInterno::class : DocumentoEntrada::class;
        $destino = $request->destino(); // 'auto' | int (id da pasta)

        // (2) Pré-validação síncrona da pasta de destino — mesma para todo o lote.
        //     Dá feedback imediato em vez de uma falha silenciosa dentro do Job.
        if ($destino !== 'auto') {
            $pasta = Pasta::find($destino);
            if (! $pasta || ! Pasta::accessibleBy($user)->whereKey($pasta->id)->exists()) {
                return response()->json([
                    'message' => 'Você não tem permissão para arquivar nesta pasta.',
                ], 403);
            }
            if (! $this->pastaService->isFolderCompatibleWithType($pasta, $type)) {
                return response()->json([
                    'message' => 'A pasta selecionada não é compatível com este tipo de documento.',
                ], 422);
            }
        }

        $dispatched = [];
        $denied = [];

        foreach ($request->input('document_ids') as $id) {
            $documento = $modelClass::find($id);

            if (! $documento) {
                $denied[] = ['id' => $id, 'reason' => 'Documento não encontrado.'];

                continue;
            }

            // (3) Autorização fina por documento.
            if (Gate::denies('archive', $documento)) {
                $denied[] = ['id' => $id, 'reason' => 'Sem permissão para arquivar este documento.'];

                continue;
            }

            // Idempotência: não re-arquivar.
            if ($documento->arquivado) {
                $denied[] = ['id' => $id, 'reason' => 'Documento já arquivado.'];

                continue;
            }

            // Assíncrono: não bloqueia a interface.
            ArchiveDocumentJob::dispatch($modelClass, (int) $id, $user->id, $destino);
            $dispatched[] = (int) $id;
        }

        return response()->json([
            'message' => count($dispatched) > 0
                ? count($dispatched).' documento(s) enviado(s) para arquivamento.'
                : 'Nenhum documento pôde ser arquivado.',
            'dispatched' => $dispatched,
            'denied' => $denied,
        ], count($dispatched) > 0 ? 200 : 422);
    }

    /**
     * Destinos de arquivamento disponíveis ao utilizador (apenas pastas acessíveis).
     *
     * NB: usa Request (não o ArchiveDocumentRequest) — este é um GET sem corpo e as
     * regras de validação do lote não se aplicam aqui.
     */
    public function destinations(Request $request): JsonResponse
    {
        $user = $request->user();

        $folders = Pasta::accessibleBy($user)
            ->orderBy('nome')
            ->get(['id', 'nome', 'type', 'parent_id', 'departamento_id'])
            ->map(fn (Pasta $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'type' => $p->type,
            ])
            ->values();

        return response()->json([
            'auto' => true, // opção de arquivamento cronológico automático
            'folders' => $folders,
        ]);
    }
}
