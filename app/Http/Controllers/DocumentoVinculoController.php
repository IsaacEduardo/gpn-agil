<?php

namespace App\Http\Controllers;

use App\Services\DocumentoVinculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentoVinculoController extends Controller
{
    public function __construct(
        protected DocumentoVinculoService $service
    ) {}

    /**
     * Retorna todos os vínculos associados a um documento (bidirecional)
     * GET /api/documentos/{tipo}/{id}/vinculos
     */
    public function index(string $tipo, int $id): JsonResponse
    {
        $vinculos = $this->service->listarVinculos($tipo, $id, Auth::user());

        return response()->json([
            'success' => true,
            'total' => count($vinculos),
            'vinculos' => $vinculos,
        ]);
    }

    /**
     * Cria novos vínculos para o documento
     * POST /api/documentos/{tipo}/{id}/vincular
     */
    public function store(Request $request, string $tipo, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'documentos' => 'nullable|array',
            'documentos.*.tipo' => 'required_with:documentos|string|in:EXTERNO,INTERNO,externo,interno',
            'documentos.*.id' => 'required_with:documentos|integer',
            // Suporte a vinculação unitária direta
            'destino_tipo' => 'nullable|string|in:EXTERNO,INTERNO,externo,interno',
            'destino_id' => 'nullable|integer',
            'relacionado_tipo' => 'nullable|string',
            'relacionado_id' => 'nullable|integer',
            'tipo_relacao' => 'required|string',
            'justificativa' => 'nullable|string|max:1000',
        ]);

        $destinos = [];

        if (! empty($validated['documentos'])) {
            $destinos = $validated['documentos'];
        } elseif (! empty($validated['destino_id'])) {
            $destinos[] = [
                'tipo' => strtoupper($validated['destino_tipo'] ?? 'EXTERNO'),
                'id' => (int) $validated['destino_id'],
            ];
        } elseif (! empty($validated['relacionado_id'])) {
            $destinos[] = [
                'tipo' => strtoupper($validated['relacionado_tipo'] ?? 'EXTERNO'),
                'id' => (int) $validated['relacionado_id'],
            ];
        }

        if (empty($destinos)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum documento selecionado para vínculo.',
                ], 422);
            }

            return back()->withErrors(['destino_id' => 'Nenhum documento selecionado para vínculo.']);
        }

        $vinculos = $this->service->vincular(
            $tipo,
            $id,
            $destinos,
            $validated['tipo_relacao'],
            $validated['justificativa'] ?? null,
            Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => count($vinculos).' documento(s) vinculado(s) com sucesso.',
                'vinculos' => $vinculos,
            ]);
        }

        return back()->with('success', 'Documento(s) vinculado(s) com sucesso.');
    }

    /**
     * Remove uma associação de vínculo
     * DELETE /api/documentos/vinculos/{vinculo_id}
     */
    public function destroy(Request $request, int $vinculo_id): JsonResponse|RedirectResponse
    {
        $this->service->desvincular($vinculo_id, Auth::user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vínculo desfeito com sucesso.',
            ]);
        }

        return back()->with('success', 'Vínculo desfeito com sucesso.');
    }

    /**
     * Pesquisa documentos em tempo real para vincular via modal
     * GET /api/documentos/{tipo}/{id}/pesquisar-vinculos
     */
    public function pesquisar(Request $request, string $tipo, int $id): JsonResponse
    {
        $termo = (string) $request->input('q', '');
        $filtroTipo = $request->input('filtro_tipo');

        $resultados = $this->service->buscarDocumentosParaVincular(
            $termo,
            $tipo,
            $id,
            Auth::user(),
            $filtroTipo
        );

        return response()->json([
            'success' => true,
            'total' => count($resultados),
            'resultados' => $resultados,
        ]);
    }
}
