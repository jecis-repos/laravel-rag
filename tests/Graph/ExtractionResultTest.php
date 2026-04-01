<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests\Graph;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PHPUnit\Framework\TestCase;

final class ExtractionResultTest extends TestCase
{
    public function test_empty_result(): void
    {
        $result = new ExtractionResult();
        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result->nodes);
        $this->assertCount(0, $result->edges);
    }

    public function test_merge(): void
    {
        $a = new ExtractionResult(
            nodes: [new KnowledgeNode('a.php', 'class', 'A', 'class A {}')],
            edges: [new KnowledgeEdge('a.php', 'B', 'extends')],
        );

        $b = new ExtractionResult(
            nodes: [new KnowledgeNode('b.php', 'class', 'B', 'class B {}')],
        );

        $merged = $a->merge($b);

        $this->assertCount(2, $merged->nodes);
        $this->assertCount(1, $merged->edges);
        $this->assertFalse($merged->isEmpty());
    }

    public function test_node_key(): void
    {
        $node = new KnowledgeNode('app/User.php', 'class', 'User', 'class User {}');
        $this->assertSame('app/User.php:class:User', $node->key());
    }

    public function test_edge_key(): void
    {
        $edge = new KnowledgeEdge('User', 'Post', 'hasMany');
        $this->assertSame('User->hasMany->Post', $edge->key());
    }
}
