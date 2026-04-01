<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests;

use Jekabs\LaravelRag\Commands\IndexCommand;
use Jekabs\LaravelRag\Commands\SearchCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests that CLI commands exist and have correct signatures.
 */
final class CommandTest extends TestCase
{
    public function test_index_command_class_exists(): void
    {
        $this->assertTrue(class_exists(IndexCommand::class));
    }

    public function test_search_command_class_exists(): void
    {
        $this->assertTrue(class_exists(SearchCommand::class));
    }

    public function test_index_command_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(IndexCommand::class);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        // Create instance without constructor side effects
        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $property->getValue($instance);

        $this->assertStringContainsString('rag:index', $signature);
        $this->assertStringContainsString('{path?', $signature);
        $this->assertStringContainsString('--extensions=', $signature);
        $this->assertStringContainsString('--fresh', $signature);
    }

    public function test_search_command_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(SearchCommand::class);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);

        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $property->getValue($instance);

        $this->assertStringContainsString('rag:search', $signature);
        $this->assertStringContainsString('{query', $signature);
        $this->assertStringContainsString('--limit=', $signature);
        $this->assertStringContainsString('--hops=', $signature);
    }

    public function test_index_command_extends_laravel_command(): void
    {
        $reflection = new ReflectionClass(IndexCommand::class);
        $this->assertTrue($reflection->isSubclassOf(\Illuminate\Console\Command::class));
    }

    public function test_search_command_extends_laravel_command(): void
    {
        $reflection = new ReflectionClass(SearchCommand::class);
        $this->assertTrue($reflection->isSubclassOf(\Illuminate\Console\Command::class));
    }

    public function test_index_command_is_final(): void
    {
        $reflection = new ReflectionClass(IndexCommand::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_search_command_is_final(): void
    {
        $reflection = new ReflectionClass(SearchCommand::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_index_command_has_handle_method(): void
    {
        $reflection = new ReflectionClass(IndexCommand::class);
        $this->assertTrue($reflection->hasMethod('handle'));

        $method = $reflection->getMethod('handle');
        $this->assertTrue($method->isPublic());
        $this->assertSame('int', $method->getReturnType()?->getName());
    }

    public function test_search_command_has_handle_method(): void
    {
        $reflection = new ReflectionClass(SearchCommand::class);
        $this->assertTrue($reflection->hasMethod('handle'));

        $method = $reflection->getMethod('handle');
        $this->assertTrue($method->isPublic());
        $this->assertSame('int', $method->getReturnType()?->getName());
    }
}
