<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag;

use Jekabs\LaravelRag\Contracts\EmbeddingDriverContract;
use Jekabs\LaravelRag\Contracts\ExtractorContract;
use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Models\KnowledgeEdgeModel;
use Jekabs\LaravelRag\Models\KnowledgeNodeModel;

/**
 * Indexes source files by running AST extractors, generating embeddings,
 * and upserting knowledge nodes and edges into the database.
 */
final class Indexer
{
    /** @var ExtractorContract[] */
    private array $extractors = [];

    public function __construct(
        private readonly EmbeddingDriverContract $embeddings,
    ) {}

    public function addExtractor(ExtractorContract $extractor): void
    {
        $this->extractors[] = $extractor;
    }

    /**
     * Index a single file.
     */
    public function indexFile(string $filePath, string $content): ExtractionResult
    {
        $result = new ExtractionResult();

        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($filePath, $content)) {
                $result = $result->merge($extractor->extract($filePath, $content));
            }
        }

        $this->persistNodes($result);
        $this->persistEdges($result);

        return $result;
    }

    /**
     * Index a directory of files recursively.
     *
     * @param string $directory Base directory to scan
     * @param string[] $extensions File extensions to include (default: ['php'])
     * @return int Number of files indexed
     */
    public function indexDirectory(string $directory, array $extensions = ['php']): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = $file->getExtension();
            if (!in_array($ext, $extensions, true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $relativePath = str_starts_with($file->getPathname(), $directory)
                ? ltrim(substr($file->getPathname(), strlen($directory)), '/')
                : $file->getPathname();

            $result = $this->indexFile($relativePath, $content);
            if (!$result->isEmpty()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove all indexed data for a given source path.
     */
    public function removeFile(string $filePath): void
    {
        KnowledgeNodeModel::where('source_path', $filePath)->delete();
        KnowledgeEdgeModel::where('source_path', $filePath)
            ->orWhere('target_path', $filePath)
            ->delete();
    }

    private function persistNodes(ExtractionResult $result): void
    {
        // Batch embed all node contents
        $contents = array_map(fn ($n) => $n->name . "\n" . $n->content, $result->nodes);
        $embeddings = $contents ? $this->embeddings->embedBatch($contents) : [];

        foreach ($result->nodes as $i => $node) {
            $vectorString = isset($embeddings[$i])
                ? '[' . implode(',', $embeddings[$i]) . ']'
                : null;

            KnowledgeNodeModel::updateOrCreate(
                [
                    'source_path' => $node->sourcePath,
                    'type' => $node->type,
                    'name' => $node->name,
                ],
                [
                    'content' => $node->content,
                    'start_line' => $node->startLine,
                    'end_line' => $node->endLine,
                    'metadata' => $node->metadata,
                    'embedding' => $vectorString,
                ],
            );
        }
    }

    private function persistEdges(ExtractionResult $result): void
    {
        foreach ($result->edges as $edge) {
            KnowledgeEdgeModel::updateOrCreate(
                [
                    'source_path' => $edge->sourcePath,
                    'target_path' => $edge->targetPath,
                    'edge_type' => $edge->edgeType,
                ],
                [
                    'metadata' => $edge->metadata,
                ],
            );
        }
    }
}
