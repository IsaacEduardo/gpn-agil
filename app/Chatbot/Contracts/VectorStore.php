<?php

namespace App\Chatbot\Contracts;

/**
 * Armazenamento e busca de vetores. A implementação MySQL guarda os embeddings
 * no banco e calcula a similaridade na aplicação; a interface permite trocar por
 * Qdrant/pgvector sem alterar os serviços.
 */
interface VectorStore
{
    /**
     * Substitui (apaga + recria) todos os chunks de um documento.
     *
     * @param  array<int, array<string,mixed>>  $chunks  Cada item: conteudo, embedding, pagina,
     *                                                    indice, tipo, anexo_id, departamento_id,
     *                                                    gabinete_id, conteudo_hash, modelo_embedding.
     */
    public function replaceDocument(string $documentableType, int $documentableId, array $chunks): void;

    public function deleteForDocument(string $documentableType, int $documentableId): void;

    /**
     * Busca os k chunks mais similares, restritos pelo filtro de permissão.
     *
     * @param  array<int, float>  $queryEmbedding
     * @param  array{is_admin:bool, departamento_ids:array<int>, gabinete_ids:array<int>, tipo?:?string}  $filter
     * @return array<int, array{chunk:\App\Chatbot\Models\ChatbotChunk, score:float}>
     */
    public function search(array $queryEmbedding, array $filter, int $k): array;

    public function flush(): void;
}
