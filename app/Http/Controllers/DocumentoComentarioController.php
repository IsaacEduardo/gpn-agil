<?php

namespace App\Http\Controllers;

use App\Models\DocumentoComentario;
use App\Models\DocumentoInterno;
use App\Services\DocumentoComentarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Comentários da edição colaborativa: listar, comentar/responder, resolver e reabrir.
 * Dentro do grupo de rotas collab (auth + feature_collab).
 */
class DocumentoComentarioController extends Controller
{
    public function __construct(private DocumentoComentarioService $comentarios) {}

    public function index(DocumentoInterno $documentoInterno): JsonResponse
    {
        $this->authorize('collaborate', $documentoInterno);

        return response()->json([
            'conversas' => $this->comentarios->conversas($documentoInterno, Auth::user()),
            'pode_comentar' => $this->comentarios->podeComentar(Auth::user(), $documentoInterno),
        ]);
    }

    public function store(Request $request, DocumentoInterno $documentoInterno): JsonResponse
    {
        abort_unless($this->comentarios->podeComentar(Auth::user(), $documentoInterno), 403, 'Sem permissão para comentar este documento.');

        $validated = $request->validate([
            'texto' => 'required|string|max:5000',
            'trecho' => 'nullable|string|max:2000',
            'parent_id' => 'nullable|integer',
        ]);

        $comentario = $this->comentarios->comentar(
            $documentoInterno,
            Auth::user(),
            $validated['texto'],
            $validated['trecho'] ?? null,
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        );

        return response()->json(['ok' => true, 'id' => $comentario->id], 201);
    }

    public function resolver(Request $request, DocumentoInterno $documentoInterno, DocumentoComentario $comentario): JsonResponse
    {
        abort_unless((int) $comentario->documento_interno_id === (int) $documentoInterno->id && $comentario->parent_id === null, 404);
        abort_unless($this->comentarios->podeResolver(Auth::user(), $comentario), 403);

        $this->comentarios->resolver($comentario, Auth::user(), $request->boolean('resolvido', true));

        return response()->json(['ok' => true]);
    }
}
