<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Extractors\ChunkExtractor;
use Jekabs\LaravelRag\Services\ChunkingService;
use PHPUnit\Framework\TestCase;

final class ChunkExtractorTest extends TestCase
{
    private ChunkExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ChunkExtractor(new ChunkingService(3200, 200));
    }

    public function test_type_is_chunk(): void
    {
        $this->assertSame('chunk', $this->extractor->type());
    }

    public function test_does_not_support_php_files(): void
    {
        $this->assertFalse($this->extractor->supports('app/Models/User.php', '<?php class User {}'));
    }

    public function test_supports_markdown(): void
    {
        $this->assertTrue($this->extractor->supports('README.md', '# Hello'));
    }

    public function test_supports_yaml(): void
    {
        $this->assertTrue($this->extractor->supports('config.yml', 'key: value'));
    }

    public function test_supports_typescript(): void
    {
        $this->assertTrue($this->extractor->supports('app.ts', 'const x = 1;'));
    }

    public function test_does_not_support_empty_content(): void
    {
        $this->assertFalse($this->extractor->supports('README.md', ''));
        $this->assertFalse($this->extractor->supports('README.md', '   '));
    }

    public function test_extracts_markdown_into_chunks(): void
    {
        $content = "# Title\n\nIntro paragraph.\n\n## Section 1\n\nBody text.";

        $result = $this->extractor->extract('docs/guide.md', $content);

        $this->assertFalse($result->isEmpty());
        $this->assertGreaterThanOrEqual(1, count($result->nodes));

        $node = $result->nodes[0];
        $this->assertSame('chunk', $node->type);
        $this->assertSame('docs/guide.md', $node->sourcePath);
        $this->assertSame('markdown', $node->metadata['format']);
        $this->assertSame(0, $node->metadata['chunk_index']);
        $this->assertArrayHasKey('chunk_total', $node->metadata);
    }

    public function test_multiple_chunks_have_sequential_indices(): void
    {
        // Create enough content to produce multiple chunks
        $sections = [];
        for ($i = 0; $i < 20; $i++) {
            $sections[] = "## Section {$i}\n\n" . str_repeat("Content for section {$i}. ", 50);
        }
        $content = implode("\n\n", $sections);

        $result = $this->extractor->extract('docs/large.md', $content);

        $this->assertGreaterThan(1, count($result->nodes));

        // Verify indices are sequential
        foreach ($result->nodes as $i => $node) {
            $this->assertSame($i, $node->metadata['chunk_index']);
        }
    }

    public function test_chunk_names_include_index(): void
    {
        $content = "# Title\n\nSome text.";
        $result = $this->extractor->extract('README.md', $content);

        $this->assertStringContainsString(':0', $result->nodes[0]->name);
    }

    public function test_no_edges_produced(): void
    {
        $content = "# Title\n\nSome text.";
        $result = $this->extractor->extract('README.md', $content);

        $this->assertEmpty($result->edges);
    }
}
