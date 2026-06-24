<?php

namespace App\Chatbot\Embeddings;

use App\Chatbot\Contracts\EmbeddingClient;

/**
 * Embeddings determinísticos para testes/demonstração (sem rede/custo).
 * Usa "feature hashing" (saco de palavras) para que textos que partilham termos
 * fiquem próximos no espaço vetorial — suficiente para validar a recuperação.
 */
class FakeEmbeddingClient implements EmbeddingClient
{
    public function __construct(private int $dimensions = 64) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function embed(string $text): array
    {
        $vec = array_fill(0, $this->dimensions, 0.0);

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            $idx = (int) (hexdec(substr(md5($token), 0, 8)) % $this->dimensions);
            $vec[$idx] += 1.0;
        }

        // Normalização L2 (cosseno fica bem definido).
        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vec)));
        if ($norm > 0) {
            $vec = array_map(fn ($v) => $v / $norm, $vec);
        }

        return $vec;
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn ($t) => $this->embed((string) $t), array_values($texts));
    }

    public function model(): string
    {
        return 'fake-embed';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
