<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Commands;

use Illuminate\Console\Command;
use Jekabs\LaravelRag\Indexer;
use Jekabs\LaravelRag\Models\KnowledgeEdgeModel;
use Jekabs\LaravelRag\Models\KnowledgeNodeModel;

final class IndexCommand extends Command
{
    protected $signature = 'rag:index {path? : Directory to index} {--extensions=php : Comma-separated file extensions} {--fresh : Clear all nodes and edges before indexing}';

    protected $description = 'Index source files into the RAG knowledge graph';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('app');
        $extensions = explode(',', $this->option('extensions'));
        $extensions = array_map('trim', $extensions);

        if ($this->option('fresh')) {
            KnowledgeNodeModel::query()->truncate();
            KnowledgeEdgeModel::query()->truncate();
            $this->info('Cleared all nodes and edges.');
        }

        $this->info("Indexing {$path}...");

        $start = microtime(true);

        /** @var Indexer $indexer */
        $indexer = app(Indexer::class);
        $indexed = $indexer->indexDirectory($path, $extensions);

        $elapsed = round(microtime(true) - $start, 1);
        $stats = $indexer->getLastIndexStats();

        $nodeCount = KnowledgeNodeModel::count();
        $edgeCount = KnowledgeEdgeModel::count();

        $this->info(
            "Scanned {$stats['scanned']} files: {$stats['skipped']} unchanged (skipped), "
            . "{$stats['indexed']} re-indexed ({$nodeCount} nodes, {$edgeCount} edges) in {$elapsed}s"
        );

        return self::SUCCESS;
    }
}
