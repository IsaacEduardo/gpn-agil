<?php

namespace App\Http\Controllers;

use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Support\DiffDocumento;

/**
 * Histórico de versões de um Documento Interno: o que mudou de uma versão para a
 * anterior (texto acrescentado e removido).
 */
class DocumentoVersaoController extends Controller
{
    public function diff(DocumentoInterno $documentoInterno, int $versao)
    {
        $this->authorize('view', $documentoInterno);

        $atual = DocumentoVersao::where('documento_interno_id', $documentoInterno->id)
            ->where('versao', $versao)
            ->latest('id')
            ->firstOrFail();

        $anterior = DocumentoVersao::where('documento_interno_id', $documentoInterno->id)
            ->where('id', '<', $atual->id)
            ->latest('id')
            ->first();

        return response()->json([
            'versao' => $atual->versao,
            'anterior' => $anterior?->versao,
            'html' => $anterior
                ? DiffDocumento::html($anterior->conteudo_final, $atual->conteudo_final)
                : '<p class="text-muted fst-italic mb-0">Esta é a primeira versão guardada: não há anterior para comparar.</p>',
        ]);
    }
}
