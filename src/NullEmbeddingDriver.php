<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag;

use Jekabs\LaravelRag\Contracts\EmbeddingDriverContract;

/**
 * Null embedding driver for testing — returns zero vectors.
 */
final class NullEmbeddingDriver implements EmbeddingDriverContract
{
    public function embed(string $text): array
    {
        return array_fill(0, (int) config('laravel-rag.embedding_dimensions', 1536), 0.0);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn () => $this->embed(''), $texts);
    }
}
