<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Contracts;

use Jekabs\LaravelRag\Graph\ExtractionResult;

/**
 * Contract for AST extractors that produce knowledge nodes and edges.
 */
interface ExtractorContract
{
    /**
     * Unique identifier for this extractor (e.g., 'class', 'method', 'relationship').
     */
    public function type(): string;

    /**
     * Whether this extractor can process the given file.
     */
    public function supports(string $filePath, string $content): bool;

    /**
     * Extract knowledge nodes and edges from the given file content.
     */
    public function extract(string $filePath, string $content): ExtractionResult;
}
