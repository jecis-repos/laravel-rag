<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Commands\IndexRepoCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IndexRepoCommandTest extends TestCase
{
    public function test_class_exists_and_is_final(): void
    {
        $this->assertTrue(class_exists(IndexRepoCommand::class));

        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_has_handle_method(): void
    {
        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $this->assertTrue($reflection->hasMethod('handle'));
    }

    public function test_resolve_repo_url_shorthand(): void
    {
        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $method = $reflection->getMethod('resolveRepoUrl');
        $method->setAccessible(true);

        $command = $reflection->newInstanceWithoutConstructor();

        // owner/repo → GitHub URL
        $this->assertSame(
            'https://github.com/laravel/framework.git',
            $method->invoke($command, 'laravel/framework'),
        );
    }

    public function test_resolve_repo_url_full_https(): void
    {
        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $method = $reflection->getMethod('resolveRepoUrl');
        $method->setAccessible(true);

        $command = $reflection->newInstanceWithoutConstructor();

        $url = 'https://github.com/laravel/framework.git';
        $this->assertSame($url, $method->invoke($command, $url));
    }

    public function test_resolve_repo_url_ssh(): void
    {
        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $method = $reflection->getMethod('resolveRepoUrl');
        $method->setAccessible(true);

        $command = $reflection->newInstanceWithoutConstructor();

        $url = 'git@github.com:laravel/framework.git';
        $this->assertSame($url, $method->invoke($command, $url));
    }

    public function test_repo_prefix_format(): void
    {
        $reflection = new ReflectionClass(IndexRepoCommand::class);
        $method = $reflection->getMethod('repoPrefix');
        $method->setAccessible(true);

        $command = $reflection->newInstanceWithoutConstructor();

        $this->assertSame(
            'github.com/laravel/framework@main',
            $method->invoke($command, 'https://github.com/laravel/framework.git', 'main'),
        );

        $this->assertSame(
            'github.com/owner/repo@develop',
            $method->invoke($command, 'git@github.com:owner/repo.git', 'develop'),
        );
    }
}
