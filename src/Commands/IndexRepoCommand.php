<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Commands;

use Illuminate\Console\Command;
use Jekabs\LaravelRag\Contracts\EmbeddingDriverContract;
use Jekabs\LaravelRag\Contracts\ExtractorContract;
use Jekabs\LaravelRag\Extractors\ChunkExtractor;
use Jekabs\LaravelRag\Indexer;
use Jekabs\LaravelRag\Models\KnowledgeEdgeModel;
use Jekabs\LaravelRag\Models\KnowledgeNodeModel;
use Jekabs\LaravelRag\Services\GitRepoSource;

/**
 * Index any external git repository into the knowledge graph.
 *
 * Usage:
 *   php artisan rag:index-repo https://github.com/laravel/framework
 *   php artisan rag:index-repo git@github.com:owner/repo.git --branch=develop
 *   php artisan rag:index-repo owner/repo --patterns="app/**\/*.php,config/*.php,README.md"
 *   php artisan rag:index-repo owner/repo --fresh --extensions="php,md,yml"
 */
final class IndexRepoCommand extends Command
{
    protected $signature = 'rag:index-repo
        {repo : Repository URL or owner/repo shorthand}
        {--branch=main : Branch to index}
        {--patterns= : Comma-separated glob patterns to filter files}
        {--extensions=php,md : Comma-separated file extensions to include}
        {--fresh : Clear all indexed data for this repo before indexing}
        {--remove : Remove the local clone and all indexed data for this repo}';

    protected $description = 'Clone and index an external git repository into the RAG knowledge graph';

    public function handle(): int
    {
        $repoInput = $this->argument('repo');
        $branch = $this->option('branch');
        $repoUrl = $this->resolveRepoUrl($repoInput);

        $source = new GitRepoSource();

        if ($this->option('remove')) {
            return $this->removeRepo($source, $repoUrl);
        }

        // Build an indexer with all configured extractors + ChunkExtractor for non-PHP
        $indexer = $this->buildIndexer();

        if ($this->option('fresh')) {
            $this->clearRepoData($repoUrl, $branch);
        }

        $this->info("Syncing {$repoUrl}@{$branch}...");
        $localPath = $source->sync($repoUrl, $branch);
        $headCommit = $source->headCommit($localPath);
        $this->line("  HEAD: {$headCommit}");

        // Get all files and filter
        $allFiles = $source->allFiles($localPath);
        $extensions = array_map('trim', explode(',', $this->option('extensions')));
        $patterns = $this->option('patterns')
            ? array_map('trim', explode(',', $this->option('patterns')))
            : [];

        // Filter by extension
        $files = array_filter($allFiles, function (string $file) use ($extensions): bool {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return in_array($ext, $extensions, true);
        });

        // Filter by glob patterns (if specified)
        if ($patterns !== []) {
            $files = $source->filterByPatterns(array_values($files), $patterns);
        }

        $files = array_values($files);
        $total = count($files);
        $this->info("Found {$total} files to index");

        if ($total === 0) {
            $this->warn('No files matched. Check --extensions and --patterns.');
            return self::SUCCESS;
        }

        // Index each file with repo-prefixed source path
        $start = microtime(true);
        $indexed = 0;
        $skipped = 0;
        $nodeCount = 0;
        $repoPrefix = $this->repoPrefix($repoUrl, $branch);

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        foreach ($files as $file) {
            $content = $source->readFile($localPath, $file);

            if ($content === null || trim($content) === '') {
                $skipped++;
                $bar->advance();
                continue;
            }

            $sourcePath = $repoPrefix . ':' . $file;
            $result = $indexer->indexFile($sourcePath, $content);

            if ($result->isEmpty()) {
                $skipped++;
            } else {
                $indexed++;
                $nodeCount += count($result->nodes);
            }

            $bar->setMessage($file);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $start, 1);
        $totalNodes = KnowledgeNodeModel::where('source_path', 'like', $repoPrefix . ':%')->count();
        $totalEdges = KnowledgeEdgeModel::where('source_path', 'like', $repoPrefix . ':%')->count();

        $this->info(
            "Indexed {$indexed} files, skipped {$skipped} unchanged ({$totalNodes} nodes, {$totalEdges} edges) in {$elapsed}s"
        );
        $this->line("  Repo prefix: <comment>{$repoPrefix}</comment>");
        $this->line("  Search with: <comment>php artisan rag:search \"your query\"</comment>");

        return self::SUCCESS;
    }

    /**
     * Resolve owner/repo shorthand to full GitHub URL.
     */
    private function resolveRepoUrl(string $input): string
    {
        // Already a URL
        if (str_starts_with($input, 'http') || str_starts_with($input, 'git@') || str_starts_with($input, 'ssh://')) {
            return $input;
        }

        // owner/repo shorthand → GitHub HTTPS
        if (preg_match('#^[\w.\-]+/[\w.\-]+$#', $input)) {
            return "https://github.com/{$input}.git";
        }

        return $input;
    }

    /**
     * Create a source_path prefix to namespace external repo content.
     *
     * e.g., "github.com/laravel/framework@main"
     */
    private function repoPrefix(string $repoUrl, string $branch): string
    {
        $normalized = preg_replace('#^(https?://|git@|ssh://)#', '', $repoUrl) ?? $repoUrl;
        $normalized = preg_replace('#\.git$#', '', $normalized) ?? $normalized;
        $normalized = str_replace(':', '/', $normalized);
        $normalized = trim($normalized, '/');

        return $normalized . '@' . $branch;
    }

    /**
     * Build an Indexer with all configured extractors + ChunkExtractor.
     */
    private function buildIndexer(): Indexer
    {
        $indexer = new Indexer(app(EmbeddingDriverContract::class));

        // Add all configured AST extractors
        foreach (config('laravel-rag.extractors', []) as $extractorClass) {
            if (class_exists($extractorClass) && is_subclass_of($extractorClass, ExtractorContract::class)) {
                $indexer->addExtractor(new $extractorClass());
            }
        }

        // Add ChunkExtractor for non-PHP files (markdown, yaml, etc.)
        $indexer->addExtractor(new ChunkExtractor());

        return $indexer;
    }

    /**
     * Clear all indexed data for a specific repo.
     */
    private function clearRepoData(string $repoUrl, string $branch): void
    {
        $prefix = $this->repoPrefix($repoUrl, $branch);

        $deleted = KnowledgeNodeModel::where('source_path', 'like', $prefix . ':%')->delete();
        KnowledgeEdgeModel::where('source_path', 'like', $prefix . ':%')
            ->orWhere('target_path', 'like', $prefix . ':%')
            ->delete();

        $this->info("Cleared {$deleted} nodes for {$prefix}");
    }

    /**
     * Remove local clone and indexed data.
     */
    private function removeRepo(GitRepoSource $source, string $repoUrl): int
    {
        $branch = $this->option('branch');
        $this->clearRepoData($repoUrl, $branch);
        $source->remove($repoUrl);

        $this->info("Removed local clone and indexed data for {$repoUrl}");

        return self::SUCCESS;
    }
}
