<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Graph;

/**
 * A directed edge in the knowledge graph connecting two source paths.
 *
 * Edge types: belongs_to, has_many, has_one, morph_to, listens_to,
 * binds_to, uses_middleware, routes_to, migrates, validates, etc.
 */
final readonly class KnowledgeEdge
{
    public function __construct(
        public string $sourcePath,
        public string $targetPath,
        public string $edgeType,
        public array $metadata = [],
    ) {}

    public function key(): string
    {
        return $this->sourcePath . '->' . $this->edgeType . '->' . $this->targetPath;
    }
}
