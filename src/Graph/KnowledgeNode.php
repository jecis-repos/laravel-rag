<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Graph;

/**
 * A knowledge node representing a code element (class, method, relationship, etc.).
 */
final readonly class KnowledgeNode
{
    public function __construct(
        public string $sourcePath,
        public string $type,
        public string $name,
        public string $content,
        public int $startLine = 0,
        public int $endLine = 0,
        public array $metadata = [],
    ) {}

    /**
     * Unique identity key for deduplication during upsert.
     */
    public function key(): string
    {
        return $this->sourcePath . ':' . $this->type . ':' . $this->name;
    }
}
