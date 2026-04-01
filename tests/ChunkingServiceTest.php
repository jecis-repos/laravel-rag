<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Services\ChunkingService;
use PHPUnit\Framework\TestCase;

final class ChunkingServiceTest extends TestCase
{
    private ChunkingService $service;

    protected function setUp(): void
    {
        $this->service = new ChunkingService(maxChunkChars: 200, overlapChars: 30);
    }

    public function test_empty_content_returns_empty(): void
    {
        $this->assertSame([], $this->service->chunk('', 'text'));
        $this->assertSame([], $this->service->chunk('   ', 'php'));
    }

    public function test_small_content_returns_single_chunk(): void
    {
        $chunks = $this->service->chunk('Hello world', 'text');

        $this->assertCount(1, $chunks);
        $this->assertSame('Hello world', $chunks[0]);
    }

    public function test_php_splits_on_class_declarations(): void
    {
        $body = str_repeat("    public function method() { return 'x'; }\n", 30);
        $php = "<?php\n\nnamespace App;\n\nclass UserController\n{\n{$body}}\n\nclass AdminController\n{\n{$body}}";

        // Use a small chunk size so the two classes can't merge
        $chunks = (new ChunkingService(maxChunkChars: 500))->chunk($php, 'php');

        $this->assertGreaterThanOrEqual(2, count($chunks));
    }

    public function test_php_splits_on_function_declarations(): void
    {
        $body = str_repeat("    \$x = 'value';\n", 30);
        $php = "<?php\n\nfunction helper_one() {\n{$body}}\n\nfunction helper_two() {\n{$body}}\n\nfunction helper_three() {\n{$body}}";

        $chunks = (new ChunkingService(maxChunkChars: 500))->chunk($php, 'php');

        $this->assertGreaterThanOrEqual(2, count($chunks));
    }

    public function test_markdown_splits_on_headers(): void
    {
        $body = str_repeat("This is a paragraph with enough content to fill space. ", 20);
        $md = "# Introduction\n\n{$body}\n\n## Chapter 1\n\n{$body}\n\n## Chapter 2\n\n{$body}";

        $chunks = (new ChunkingService(maxChunkChars: 500))->chunk($md, 'markdown');

        $this->assertGreaterThanOrEqual(2, count($chunks));
        $this->assertStringContainsString('Introduction', $chunks[0]);
    }

    public function test_text_splits_on_paragraphs(): void
    {
        $text = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

        $chunks = (new ChunkingService(maxChunkChars: 5000))->chunk($text, 'text');

        $this->assertGreaterThanOrEqual(1, count($chunks));
    }

    public function test_small_blocks_are_merged(): void
    {
        // 3 tiny paragraphs should merge into one chunk
        $text = "A.\n\nB.\n\nC.";

        $chunks = $this->service->chunk($text, 'text');

        // All under 200 chars, so should be a single chunk
        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('A.', $chunks[0]);
        $this->assertStringContainsString('C.', $chunks[0]);
    }

    public function test_large_blocks_create_overlap(): void
    {
        // Create content that exceeds chunk size
        $block1 = str_repeat('A', 150);
        $block2 = str_repeat('B', 150);
        $text = $block1 . "\n\n" . $block2;

        $chunks = $this->service->chunk($text, 'text');

        // Should be 2 chunks since combined > 200
        $this->assertCount(2, $chunks);

        // Second chunk should start with overlap from first
        $this->assertStringContainsString('A', $chunks[1]);
        $this->assertStringContainsString('B', $chunks[1]);
    }

    public function test_detect_format(): void
    {
        $this->assertSame('php', $this->service->detectFormat('app/Models/User.php'));
        $this->assertSame('markdown', $this->service->detectFormat('docs/README.md'));
        $this->assertSame('markdown', $this->service->detectFormat('page.mdx'));
        $this->assertSame('javascript', $this->service->detectFormat('app.ts'));
        $this->assertSame('javascript', $this->service->detectFormat('Component.tsx'));
        $this->assertSame('yaml', $this->service->detectFormat('docker-compose.yml'));
        $this->assertSame('json', $this->service->detectFormat('package.json'));
        $this->assertSame('python', $this->service->detectFormat('script.py'));
        $this->assertSame('text', $this->service->detectFormat('Makefile'));
    }

    public function test_php_splits_on_final_readonly_class(): void
    {
        $body = str_repeat("    public string \$field;\n", 30);
        $php = "<?php\n\nfinal readonly class ValueObject\n{\n{$body}}\n\nfinal class Service\n{\n{$body}}";

        $chunks = (new ChunkingService(maxChunkChars: 500))->chunk($php, 'php');

        $this->assertGreaterThanOrEqual(2, count($chunks));
    }
}
