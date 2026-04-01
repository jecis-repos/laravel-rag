<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Contracts\ExtractorContract;
use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use Jekabs\LaravelRag\Services\ChunkingService;

/**
 * Generic extractor that chunks content by format for files
 * that other AST extractors don't handle (markdown, YAML, config, etc.).
 *
 * Runs AFTER all AST extractors — only processes files that no other
 * extractor supports, or non-PHP files in multi-extension indexing.
 */
final class ChunkExtractor implements ExtractorContract
{
    /** @var list<string> Extensions that PHP AST extractors already handle */
    private const array AST_EXTENSIONS = ['php'];

    private readonly ChunkingService $chunking;

    public function __construct(?ChunkingService $chunking = null)
    {
        if ($chunking !== null) {
            $this->chunking = $chunking;
            return;
        }

        $maxChunk = function_exists('config') && app()->bound('config')
            ? (int) config('laravel-rag.chunking.max_chunk_size', 3200)
            : 3200;
        $overlap = function_exists('config') && app()->bound('config')
            ? (int) config('laravel-rag.chunking.overlap', 200)
            : 200;

        $this->chunking = new ChunkingService($maxChunk, $overlap);
    }

    public function type(): string
    {
        return 'chunk';
    }

    /**
     * Supports any non-PHP file, or PHP files without classes/functions
     * (e.g., config files that aren't caught by ConfigExtractor).
     */
    public function supports(string $filePath, string $content): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Skip PHP files — they're handled by the 12 AST extractors
        if (in_array($ext, self::AST_EXTENSIONS, true)) {
            return false;
        }

        // Support everything else: markdown, yaml, json, js, ts, etc.
        return trim($content) !== '';
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $format = $this->chunking->detectFormat($filePath);
        $chunks = $this->chunking->chunk($content, $format);

        if ($chunks === []) {
            return new ExtractionResult();
        }

        $nodes = [];

        foreach ($chunks as $index => $chunk) {
            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'chunk',
                name: pathinfo($filePath, PATHINFO_FILENAME) . ':' . $index,
                content: $chunk,
                metadata: [
                    'format' => $format,
                    'chunk_index' => $index,
                    'chunk_total' => count($chunks),
                    'original_path' => $filePath,
                ],
            );
        }

        return new ExtractionResult($nodes);
    }
}
