<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Contracts;

interface EmbeddingDriverContract
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @return float[] Vector of configured dimensions
     */
    public function embed(string $text): array;

    /**
     * Generate embedding vectors for multiple texts in a single batch.
     *
     * @param string[] $texts
     * @return float[][] Array of vectors
     */
    public function embedBatch(array $texts): array;
}
