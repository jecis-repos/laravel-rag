<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Commands;

use Illuminate\Console\Command;
use Jekabs\LaravelRag\Search\KnowledgeSearch;

final class SearchCommand extends Command
{
    protected $signature = 'rag:search {query : Search query} {--limit=10 : Max results} {--hops=0 : Override max hops (0 = use config default)}';

    protected $description = 'Search the RAG knowledge graph';

    public function handle(): int
    {
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');
        $hops = (int) $this->option('hops');

        /** @var KnowledgeSearch $search */
        $search = app(KnowledgeSearch::class);

        $maxHops = $hops > 0 ? $hops : (int) config('laravel-rag.search.max_hops', 2);

        $results = $search->search($query, $limit, $maxHops);

        if (empty($results)) {
            $this->info('No results found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                round($result->score, 4),
                $result->type,
                $result->name,
                $result->sourcePath,
                $result->hop,
                $result->matchType,
            ];
        }

        $this->table(
            ['Score', 'Type', 'Name', 'Source Path', 'Hop', 'Match Type'],
            $rows,
        );

        return self::SUCCESS;
    }
}
