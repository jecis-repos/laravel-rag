<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $sourcePath,
        public string $type,
        public string $name,
        public string $content,
        public float $score,
        public int $hop = 0,
        public string $matchType = 'semantic',
        public array $metadata = [],
    ) {}
}
