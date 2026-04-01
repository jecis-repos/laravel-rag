<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Search;

use Illuminate\Support\Facades\DB;
use Jekabs\LaravelRag\Contracts\EmbeddingDriverContract;
use Jekabs\LaravelRag\Models\KnowledgeEdgeModel;
use Jekabs\LaravelRag\Models\KnowledgeNodeModel;

/**
 * Multi-stage search pipeline:
 *   1. pgvector cosine similarity (semantic)
 *   2. ILIKE keyword fallback (exact match)
 *   3. Reciprocal Rank Fusion to merge results
 *   4. Multi-hop graph traversal with score decay
 *
 * Optionally applies recency boost for recently modified files.
 */
final class KnowledgeSearch
{
    public function __construct(
        private readonly EmbeddingDriverContract $embeddings,
    ) {}

    /**
     * @return SearchResult[]
     */
    public function search(string $query, int $topK = 0, int $maxHops = 0): array
    {
        $config = config('laravel-rag.search');
        $topK = $topK ?: $config['top_k'];
        $maxHops = $maxHops ?: $config['max_hops'];

        // Stage 1: Semantic search via pgvector
        $semanticResults = $this->semanticSearch($query, $topK);

        // Stage 2: Keyword search via ILIKE
        $keywordResults = $this->keywordSearch($query, $topK);

        // Stage 3: Reciprocal Rank Fusion
        $fused = $this->reciprocalRankFusion($semanticResults, $keywordResults, $config['rrf_k']);

        // Stage 4: Multi-hop graph traversal
        if ($maxHops > 0) {
            $fused = $this->multiHopExpand($fused, $maxHops, $config['hop_penalty']);
        }

        // Apply recency boost
        $fused = $this->applyRecencyBoost($fused, $config['recency_days'], $config['recency_boost']);

        // Sort by score descending and limit
        usort($fused, fn (SearchResult $a, SearchResult $b) => $b->score <=> $a->score);

        return array_slice($fused, 0, $topK);
    }

    /**
     * @return SearchResult[]
     */
    private function semanticSearch(string $query, int $topK): array
    {
        $embedding = $this->embeddings->embed($query);
        $vectorString = '[' . implode(',', $embedding) . ']';
        $connection = config('laravel-rag.connection', 'pgsql');
        $table = config('laravel-rag.tables.nodes', 'rag_knowledge_nodes');

        $rows = DB::connection($connection)
            ->select(
                "SELECT source_path, type, name, content, metadata,
                        1 - (embedding <=> ?::vector) as score
                 FROM {$table}
                 WHERE embedding IS NOT NULL
                 ORDER BY embedding <=> ?::vector
                 LIMIT ?",
                [$vectorString, $vectorString, $topK]
            );

        return array_map(fn ($row) => new SearchResult(
            sourcePath: $row->source_path,
            type: $row->type,
            name: $row->name,
            content: $row->content,
            score: (float) $row->score,
            matchType: 'semantic',
            metadata: json_decode($row->metadata ?? '{}', true) ?: [],
        ), $rows);
    }

    /**
     * @return SearchResult[]
     */
    private function keywordSearch(string $query, int $topK): array
    {
        $rows = KnowledgeNodeModel::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('content', 'ILIKE', "%{$query}%");
            })
            ->limit($topK)
            ->get();

        $bm25 = new BM25(
            config('laravel-rag.search.bm25_k1', 1.5),
            config('laravel-rag.search.bm25_b', 0.75),
        );

        // Index all results for BM25 scoring
        foreach ($rows as $i => $row) {
            $bm25->addDocument($i, $row->name . ' ' . $row->content);
        }

        $results = [];
        foreach ($rows as $i => $row) {
            $results[] = new SearchResult(
                sourcePath: $row->source_path,
                type: $row->type,
                name: $row->name,
                content: $row->content,
                score: $bm25->score($i, $query),
                matchType: 'keyword',
                metadata: $row->metadata ?? [],
            );
        }

        return $results;
    }

    /**
     * Reciprocal Rank Fusion: merge two ranked lists with RRF scores.
     *
     * @return SearchResult[]
     */
    private function reciprocalRankFusion(array $semanticResults, array $keywordResults, int $k): array
    {
        $scores = [];
        $resultMap = [];

        foreach ($semanticResults as $rank => $result) {
            $key = $result->sourcePath . ':' . $result->name;
            $scores[$key] = ($scores[$key] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $resultMap[$key] = $result;
        }

        foreach ($keywordResults as $rank => $result) {
            $key = $result->sourcePath . ':' . $result->name;
            $scores[$key] = ($scores[$key] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $resultMap[$key] ??= $result;
        }

        $fused = [];
        foreach ($scores as $key => $score) {
            $original = $resultMap[$key];
            $fused[] = new SearchResult(
                sourcePath: $original->sourcePath,
                type: $original->type,
                name: $original->name,
                content: $original->content,
                score: $score,
                matchType: 'rrf',
                metadata: $original->metadata,
            );
        }

        return $fused;
    }

    /**
     * Expand results by traversing knowledge graph edges.
     * Each hop applies a score penalty.
     *
     * @return SearchResult[]
     */
    private function multiHopExpand(array $results, int $maxHops, float $hopPenalty): array
    {
        $seen = [];
        foreach ($results as $r) {
            $seen[$r->sourcePath . ':' . $r->name] = true;
        }

        $expanded = $results;

        for ($hop = 1; $hop <= $maxHops; $hop++) {
            $newResults = [];

            foreach ($results as $result) {
                // Find edges from this node's source path
                $edges = KnowledgeEdgeModel::query()
                    ->where('source_path', $result->sourcePath)
                    ->orWhere('target_path', $result->sourcePath)
                    ->get();

                foreach ($edges as $edge) {
                    $targetPath = $edge->source_path === $result->sourcePath
                        ? $edge->target_path
                        : $edge->source_path;

                    $hopNodes = KnowledgeNodeModel::query()
                        ->where('source_path', $targetPath)
                        ->get();

                    foreach ($hopNodes as $node) {
                        $key = $node->source_path . ':' . $node->name;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $hopScore = $result->score - ($hop * $hopPenalty);
                        if ($hopScore <= 0) {
                            continue;
                        }

                        $hopResult = new SearchResult(
                            sourcePath: $node->source_path,
                            type: $node->type,
                            name: $node->name,
                            content: $node->content,
                            score: $hopScore,
                            hop: $hop,
                            matchType: 'graph_hop',
                            metadata: array_merge(
                                $node->metadata ?? [],
                                ['edge_type' => $edge->edge_type, 'hop' => $hop],
                            ),
                        );

                        $newResults[] = $hopResult;
                    }
                }
            }

            $expanded = [...$expanded, ...$newResults];
            $results = $newResults; // Next hop starts from these
        }

        return $expanded;
    }

    /**
     * Boost scores for recently modified files.
     *
     * @return SearchResult[]
     */
    private function applyRecencyBoost(array $results, int $days, float $boost): array
    {
        $cutoff = now()->subDays($days);

        return array_map(function (SearchResult $r) use ($cutoff, $boost) {
            $node = KnowledgeNodeModel::query()
                ->where('source_path', $r->sourcePath)
                ->where('name', $r->name)
                ->first();

            if ($node && $node->updated_at && $node->updated_at->gte($cutoff)) {
                return new SearchResult(
                    sourcePath: $r->sourcePath,
                    type: $r->type,
                    name: $r->name,
                    content: $r->content,
                    score: $r->score * $boost,
                    hop: $r->hop,
                    matchType: $r->matchType,
                    metadata: $r->metadata,
                );
            }

            return $r;
        }, $results);
    }
}
