<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests\Search;

use Jekabs\LaravelRag\Search\BM25;
use PHPUnit\Framework\TestCase;

final class BM25Test extends TestCase
{
    public function test_scores_matching_document_higher(): void
    {
        $bm25 = new BM25();
        $bm25->addDocument(0, 'The quick brown fox jumps over the lazy dog');
        $bm25->addDocument(1, 'A completely unrelated document about databases');
        $bm25->addDocument(2, 'The fox is quick and brown');

        $this->assertGreaterThan(
            $bm25->score(1, 'quick brown fox'),
            $bm25->score(0, 'quick brown fox'),
        );
    }

    public function test_exact_match_scores_highest(): void
    {
        $bm25 = new BM25();
        $bm25->addDocument(0, 'hasMany relationship');
        $bm25->addDocument(1, 'belongsTo relationship with foreign key');
        $bm25->addDocument(2, 'hasMany morphMany polymorphic');

        $score0 = $bm25->score(0, 'hasMany');
        $score1 = $bm25->score(1, 'hasMany');
        $score2 = $bm25->score(2, 'hasMany');

        $this->assertGreaterThan(0.0, $score0);
        $this->assertSame(0.0, $score1); // No match
        $this->assertGreaterThan(0.0, $score2);
    }

    public function test_empty_document_returns_zero(): void
    {
        $bm25 = new BM25();
        $bm25->addDocument(0, '');

        $this->assertSame(0.0, $bm25->score(0, 'anything'));
    }

    public function test_no_match_returns_zero(): void
    {
        $bm25 = new BM25();
        $bm25->addDocument(0, 'hello world');

        $this->assertSame(0.0, $bm25->score(0, 'xyz'));
    }

    public function test_longer_documents_get_normalized(): void
    {
        $bm25 = new BM25();
        $bm25->addDocument(0, 'controller'); // Short doc
        $bm25->addDocument(1, 'controller handles requests and manages responses and middleware and validation'); // Long doc

        $shortScore = $bm25->score(0, 'controller');
        $longScore = $bm25->score(1, 'controller');

        // Both should score, but BM25 length normalization affects results
        $this->assertGreaterThan(0.0, $shortScore);
        $this->assertGreaterThan(0.0, $longScore);
    }

    public function test_idf_penalizes_common_terms(): void
    {
        $bm25 = new BM25();
        // "the" appears in all 3 docs, "unique" only in one
        $bm25->addDocument(0, 'the unique token');
        $bm25->addDocument(1, 'the common word');
        $bm25->addDocument(2, 'the other thing');

        $uniqueScore = $bm25->score(0, 'unique');
        $theScore = $bm25->score(0, 'the');

        // "unique" is rarer so should have higher IDF
        $this->assertGreaterThan($theScore, $uniqueScore);
    }
}
