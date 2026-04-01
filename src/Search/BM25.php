<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Search;

/**
 * BM25 keyword scoring for a collection of documents.
 *
 * Implements Okapi BM25 with configurable k1 and b parameters.
 */
final class BM25
{
    /** @var array<int, array<string, int>> term frequencies per doc */
    private array $termFreqs = [];

    /** @var array<string, int> document frequency per term */
    private array $docFreqs = [];

    /** @var array<int, int> document lengths */
    private array $docLengths = [];

    private float $avgDocLength = 0.0;
    private int $docCount = 0;

    public function __construct(
        private readonly float $k1 = 1.5,
        private readonly float $b = 0.75,
    ) {}

    /**
     * Index a document for BM25 scoring.
     */
    public function addDocument(int $id, string $text): void
    {
        $terms = $this->tokenize($text);
        $this->docLengths[$id] = count($terms);
        $this->docCount++;
        $this->avgDocLength = array_sum($this->docLengths) / $this->docCount;

        $tf = [];
        $seenTerms = [];
        foreach ($terms as $term) {
            $tf[$term] = ($tf[$term] ?? 0) + 1;
            if (!isset($seenTerms[$term])) {
                $this->docFreqs[$term] = ($this->docFreqs[$term] ?? 0) + 1;
                $seenTerms[$term] = true;
            }
        }
        $this->termFreqs[$id] = $tf;
    }

    /**
     * Score a document against a query.
     */
    public function score(int $docId, string $query): float
    {
        $queryTerms = $this->tokenize($query);
        $tf = $this->termFreqs[$docId] ?? [];
        $docLength = $this->docLengths[$docId] ?? 0;

        if ($docLength === 0) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($queryTerms as $term) {
            $termFreq = $tf[$term] ?? 0;
            $docFreq = $this->docFreqs[$term] ?? 0;

            if ($termFreq === 0 || $docFreq === 0) {
                continue;
            }

            // IDF with smoothing
            $idf = log(($this->docCount - $docFreq + 0.5) / ($docFreq + 0.5) + 1.0);

            // BM25 TF component
            $tfNorm = ($termFreq * ($this->k1 + 1.0))
                / ($termFreq + $this->k1 * (1.0 - $this->b + $this->b * ($docLength / $this->avgDocLength)));

            $score += $idf * $tfNorm;
        }

        return $score;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9_\s]/', ' ', $text) ?? $text;

        return array_values(array_filter(
            preg_split('/\s+/', $text) ?: [],
            fn (string $t) => strlen($t) >= 2,
        ));
    }
}
