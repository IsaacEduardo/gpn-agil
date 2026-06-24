<?php

namespace App\Chatbot\VectorStore;

use App\Chatbot\Contracts\VectorStore;
use App\Chatbot\Models\ChatbotChunk;
use Illuminate\Support\Facades\DB;

/**
 * Vector store baseado em MySQL: guarda embeddings (JSON) e calcula a similaridade
 * de cosseno na aplicação, sobre candidatos pré-filtrados por permissão.
 */
class MysqlVectorStore implements VectorStore
{
    /**
     * @param  array<string,mixed>  $config  config('chatbot.vector_store')
     */
    public function __construct(private array $config) {}

    public function replaceDocument(string $documentableType, int $documentableId, array $chunks): void
    {
        DB::transaction(function () use ($documentableType, $documentableId, $chunks) {
            ChatbotChunk::where('documentable_type', $documentableType)
                ->where('documentable_id', $documentableId)
                ->delete();

            foreach ($chunks as $c) {
                ChatbotChunk::create(array_merge($c, [
                    'documentable_type' => $documentableType,
                    'documentable_id' => $documentableId,
                ]));
            }
        });
    }

    public function deleteForDocument(string $documentableType, int $documentableId): void
    {
        ChatbotChunk::where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->delete();
    }

    public function search(array $queryEmbedding, array $filter, int $k): array
    {
        $query = ChatbotChunk::query();

        // Pré-filtro de permissão (defesa em profundidade complementada pela re-autorização no retriever).
        if (empty($filter['is_admin'])) {
            $deps = $filter['departamento_ids'] ?? [];
            $gabs = $filter['gabinete_ids'] ?? [];

            if (empty($deps) && empty($gabs)) {
                return [];
            }

            $query->where(function ($q) use ($deps, $gabs) {
                if (! empty($deps)) {
                    $q->whereIn('departamento_id', $deps);
                }
                if (! empty($gabs)) {
                    $q->orWhereIn('gabinete_id', $gabs);
                }
            });
        }

        if (! empty($filter['tipo'])) {
            $query->where('tipo', $filter['tipo']);
        }

        $candidates = $query->latest('id')
            ->limit((int) ($this->config['candidate_limit'] ?? 500))
            ->get();

        $minScore = (float) ($this->config['min_score'] ?? 0.0);
        $scored = [];
        foreach ($candidates as $chunk) {
            $emb = $chunk->embedding;
            if (! is_array($emb) || $emb === []) {
                continue;
            }
            $score = $this->cosine($queryEmbedding, $emb);
            if ($score < $minScore) {
                continue;
            }
            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    public function flush(): void
    {
        ChatbotChunk::query()->delete();
    }

    /**
     * @param  array<int,float>  $a
     * @param  array<int,float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
