<?php

namespace App\Chatbot\Services;

use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Contracts\VectorStore;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use Illuminate\Database\Eloquent\Model;

/**
 * Orquestra a indexação de um documento: extrai/fragmenta (chunker), gera embeddings
 * e substitui os chunks no vector store. Denormaliza departamento/gabinete para o
 * pré-filtro de permissão na busca.
 */
class DocumentIndexer
{
    public function __construct(
        private DocumentChunker $chunker,
        private EmbeddingClient $embeddings,
        private VectorStore $vectorStore,
    ) {}

    public function index(Model $documento): int
    {
        if ($documento instanceof DocumentoEntrada) {
            return $this->indexEntrada($documento);
        }
        if ($documento instanceof DocumentoInterno) {
            return $this->indexInterno($documento);
        }

        return 0;
    }

    public function indexEntrada(DocumentoEntrada $doc): int
    {
        $doc->loadMissing('departamento');

        return $this->persist(DocumentoEntrada::class, $doc->id, $this->chunker->chunkEntrada($doc), [
            'departamento_id' => $doc->departamento_id,
            'gabinete_id' => optional($doc->departamento)->gabinete_id,
        ]);
    }

    public function indexInterno(DocumentoInterno $doc): int
    {
        $doc->loadMissing('departamento');

        return $this->persist(DocumentoInterno::class, $doc->id, $this->chunker->chunkInterno($doc), [
            'departamento_id' => $doc->departamento_id,
            'gabinete_id' => optional($doc->departamento)->gabinete_id,
        ]);
    }

    public function remove(string $documentableType, int $documentableId): void
    {
        $this->vectorStore->deleteForDocument($documentableType, $documentableId);
    }

    /**
     * @param  array<int, array<string,mixed>>  $chunks
     * @param  array{departamento_id:?int, gabinete_id:?int}  $perm
     */
    private function persist(string $type, int $id, array $chunks, array $perm): int
    {
        if (empty($chunks)) {
            $this->vectorStore->deleteForDocument($type, $id);

            return 0;
        }

        $embeddings = $this->embeddings->embedBatch(array_map(fn ($c) => $c['conteudo'], $chunks));
        $modelo = $this->embeddings->model();

        $rows = [];
        foreach ($chunks as $i => $c) {
            $rows[] = [
                'tipo' => $c['tipo'],
                'anexo_id' => $c['anexo_id'] ?? null,
                'pagina' => $c['pagina'] ?? null,
                'indice' => $c['indice'] ?? $i,
                'conteudo' => $c['conteudo'],
                'conteudo_hash' => hash('sha256', $c['conteudo']),
                'departamento_id' => $perm['departamento_id'] ?? null,
                'gabinete_id' => $perm['gabinete_id'] ?? null,
                'embedding' => $embeddings[$i] ?? [],
                'modelo_embedding' => $modelo,
            ];
        }

        $this->vectorStore->replaceDocument($type, $id, $rows);

        return count($rows);
    }
}
