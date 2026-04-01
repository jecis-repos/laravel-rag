<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Graph;

/**
 * Immutable result from an AST extractor — a collection of knowledge nodes and edges.
 */
final readonly class ExtractionResult
{
    /**
     * @param KnowledgeNode[] $nodes
     * @param KnowledgeEdge[] $edges
     */
    public function __construct(
        public array $nodes = [],
        public array $edges = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            nodes: [...$this->nodes, ...$other->nodes],
            edges: [...$this->edges, ...$other->edges],
        );
    }

    public function isEmpty(): bool
    {
        return count($this->nodes) === 0 && count($this->edges) === 0;
    }
}
