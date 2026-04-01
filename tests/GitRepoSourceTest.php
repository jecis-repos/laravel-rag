<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Services\GitRepoSource;
use PHPUnit\Framework\TestCase;

final class GitRepoSourceTest extends TestCase
{
    public function test_filter_by_patterns_matches_php_files(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');

        $files = [
            'app/Models/User.php',
            'app/Models/Post.php',
            'app/Http/Controllers/UserController.php',
            'config/app.php',
            'README.md',
            'package.json',
            'resources/views/home.blade.php',
        ];

        $matched = $source->filterByPatterns($files, ['app/Models/*.php']);

        $this->assertCount(2, $matched);
        $this->assertContains('app/Models/User.php', $matched);
        $this->assertContains('app/Models/Post.php', $matched);
    }

    public function test_filter_by_patterns_matches_multiple_patterns(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');

        $files = [
            'app/Models/User.php',
            'config/app.php',
            'README.md',
            'docs/guide.md',
        ];

        $matched = $source->filterByPatterns($files, ['config/*.php', '*.md']);

        $this->assertCount(2, $matched);
        $this->assertContains('config/app.php', $matched);
        $this->assertContains('README.md', $matched);
    }

    public function test_filter_by_empty_patterns_returns_all(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');

        $files = ['a.php', 'b.md', 'c.yml'];
        $matched = $source->filterByPatterns($files, []);

        $this->assertSame($files, $matched);
    }

    public function test_filter_by_patterns_no_match(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');

        $files = ['app/Models/User.php', 'config/app.php'];
        $matched = $source->filterByPatterns($files, ['*.go']);

        $this->assertEmpty($matched);
    }

    public function test_read_file_returns_null_for_missing(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');

        $this->assertNull($source->readFile('/nonexistent/path', 'file.txt'));
    }

    public function test_read_file_returns_content(): void
    {
        $source = new GitRepoSource(sys_get_temp_dir() . '/laravel-rag-test');
        $dir = sys_get_temp_dir() . '/laravel-rag-read-test-' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/test.txt', 'hello world');

        $content = $source->readFile($dir, 'test.txt');

        $this->assertSame('hello world', $content);

        unlink($dir . '/test.txt');
        rmdir($dir);
    }
}
