<?php

namespace App\Chatbot\Services;

use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Contracts\VectorStore;
use App\Chatbot\Models\ChatbotChunk;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\DocumentoPermissionService;
use Illuminate\Support\Facades\Gate;

/**
 * Recuperação RAG "permissões primeiro": filtra por departamento/gabinete na busca
 * vetorial, opcionalmente combina com palavra-chave (híbrido) e RE-AUTORIZA cada
 * documento citado (defesa em profundidade) antes de devolver contexto e fontes.
 */
class ChatbotRetriever
{
    /** @var array<string, ?\Illuminate\Database\Eloquent\Model> */
    private array $parentCache = [];

    /** @var array<string, bool> */
    private array $authCache = [];

    public function __construct(
        private EmbeddingClient $embeddings,
        private VectorStore $vectorStore,
        private DocumentoPermissionService $permissions,
    ) {}

    /**
     * @return array{items: array<int, array<string,mixed>>, fontes: array<int, array<string,mixed>>}
     */
    public function retrieve(User $user, string $question): array
    {
        $this->parentCache = [];
        $this->authCache = [];

        $k = (int) config('chatbot.vector_store.top_k', 6);
        $filter = $this->permissionFilter($user);

        if (! $filter['is_admin'] && empty($filter['departamento_ids']) && empty($filter['gabinete_ids'])) {
            return ['items' => [], 'fontes' => []];
        }

        $queryEmbedding = $this->embeddings->embed($question);
        $hits = $this->vectorStore->search($queryEmbedding, $filter, $k * 2);

        // Híbrido: acrescenta chunks que casam por palavra-chave (mesmo pré-filtro de permissão).
        if (config('chatbot.hybrid', true)) {
            $hits = $this->mergeKeyword($hits, $question, $filter, $k);
        }

        $items = [];
        $fontes = [];
        $refByDoc = [];
        $n = 0;

        foreach ($hits as $hit) {
            /** @var ChatbotChunk $chunk */
            $chunk = $hit['chunk'];
            if (! $this->canView($user, $chunk)) {
                continue; // re-autorização por documento
            }

            $docKey = $chunk->documentable_type.':'.$chunk->documentable_id;
            if (! isset($refByDoc[$docKey])) {
                $n++;
                $refByDoc[$docKey] = "Doc {$n}";
                $fontes[] = $this->fonte($chunk, $refByDoc[$docKey]);
            }

            $items[] = [
                'ref' => $refByDoc[$docKey],
                'pagina' => $chunk->pagina,
                'conteudo' => $chunk->conteudo,
            ];

            if (count($items) >= $k) {
                break;
            }
        }

        return ['items' => $items, 'fontes' => $fontes];
    }

    /**
     * @return array{is_admin:bool, departamento_ids:array<int>, gabinete_ids:array<int>}
     */
    private function permissionFilter(User $user): array
    {
        return [
            'is_admin' => $this->permissions->isAdmin($user),
            'departamento_ids' => $this->permissions->getUserDepartments($user),
            'gabinete_ids' => $this->permissions->getUserResponsibleGabinetes($user),
        ];
    }

    /**
     * @param  array<int, array{chunk:ChatbotChunk, score:float}>  $hits
     * @param  array<string,mixed>  $filter
     * @return array<int, array{chunk:ChatbotChunk, score:float}>
     */
    private function mergeKeyword(array $hits, string $question, array $filter, int $k): array
    {
        $termo = trim($question);
        if ($termo === '') {
            return $hits;
        }

        $query = ChatbotChunk::query()->where('conteudo', 'like', '%'.$termo.'%');

        if (empty($filter['is_admin'])) {
            $deps = $filter['departamento_ids'];
            $gabs = $filter['gabinete_ids'];
            $query->where(function ($q) use ($deps, $gabs) {
                if (! empty($deps)) {
                    $q->whereIn('departamento_id', $deps);
                }
                if (! empty($gabs)) {
                    $q->orWhereIn('gabinete_id', $gabs);
                }
            });
        }

        $seen = [];
        foreach ($hits as $h) {
            $seen[$h['chunk']->id] = true;
        }
        foreach ($query->limit($k)->get() as $chunk) {
            if (! isset($seen[$chunk->id])) {
                $hits[] = ['chunk' => $chunk, 'score' => 0.0];
            }
        }

        return $hits;
    }

    private function canView(User $user, ChatbotChunk $chunk): bool
    {
        $key = $chunk->documentable_type.':'.$chunk->documentable_id;
        if (array_key_exists($key, $this->authCache)) {
            return $this->authCache[$key];
        }

        $parent = $this->resolveParent($chunk);
        $allowed = false;
        if ($parent instanceof DocumentoEntrada) {
            $allowed = $this->permissions->canViewDocument($user, $parent);
        } elseif ($parent instanceof DocumentoInterno) {
            $allowed = Gate::forUser($user)->allows('view', $parent);
        }

        return $this->authCache[$key] = $allowed;
    }

    private function resolveParent(ChatbotChunk $chunk): ?\Illuminate\Database\Eloquent\Model
    {
        $key = $chunk->documentable_type.':'.$chunk->documentable_id;
        if (! array_key_exists($key, $this->parentCache)) {
            $this->parentCache[$key] = $chunk->documentable_type::find($chunk->documentable_id);
        }

        return $this->parentCache[$key];
    }

    private function fonte(ChatbotChunk $chunk, string $ref): array
    {
        $parent = $this->resolveParent($chunk);

        if ($parent instanceof DocumentoEntrada) {
            $titulo = trim(($parent->numero_sequencial ? $parent->numero_sequencial.'/'.$parent->ano_referencia.' — ' : '')
                .($parent->assunto ?? 'Documento de entrada'));

            return [
                'ref' => $ref,
                'tipo' => 'externo',
                'tipo_label' => 'Documento de entrada',
                'titulo' => $titulo,
                'pagina' => $chunk->pagina,
                'url' => route('documentos-entradas.show', $chunk->documentable_id),
            ];
        }

        if ($parent instanceof DocumentoInterno) {
            $titulo = trim(($parent->numero_referencia ? $parent->numero_referencia.' — ' : '')
                .($parent->titulo ?? 'Documento interno'));

            return [
                'ref' => $ref,
                'tipo' => 'interno',
                'tipo_label' => 'Documento interno',
                'titulo' => $titulo,
                'pagina' => null,
                'url' => route('documentos-internos.show', $chunk->documentable_id),
            ];
        }

        return ['ref' => $ref, 'tipo' => 'desconhecido', 'tipo_label' => 'Documento', 'titulo' => 'Documento', 'pagina' => null, 'url' => '#'];
    }
}
