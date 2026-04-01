<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Tests\Extractors;

use Jekabs\LaravelRag\Extractors\ClassExtractor;
use PHPUnit\Framework\TestCase;

final class ClassExtractorTest extends TestCase
{
    private ClassExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ClassExtractor();
    }

    public function test_extracts_class(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Models;

        class User extends Model implements Authenticatable
        {
            protected $table = 'users';
        }
        PHP;

        $result = $this->extractor->extract('app/Models/User.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('App\Models\User', $result->nodes[0]->name);
        $this->assertSame('class', $result->nodes[0]->type);
        $this->assertSame('Model', $result->nodes[0]->metadata['extends']);
        $this->assertContains('Authenticatable', $result->nodes[0]->metadata['implements']);
    }

    public function test_creates_extends_edge(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;

        class UserController extends Controller {}
        PHP;

        $result = $this->extractor->extract('app/UserController.php', $code);

        $this->assertCount(1, $result->edges);
        $this->assertSame('extends', $result->edges[0]->edgeType);
        $this->assertSame('Controller', $result->edges[0]->targetPath);
    }

    public function test_creates_implements_edges(): void
    {
        $code = <<<'PHP'
        <?php
        class Payment implements Billable, Refundable {}
        PHP;

        $result = $this->extractor->extract('Payment.php', $code);

        $edgeTypes = array_map(fn ($e) => $e->edgeType, $result->edges);
        $targets = array_map(fn ($e) => $e->targetPath, $result->edges);

        $this->assertCount(2, $result->edges); // 2 implements edges
        $this->assertContains('Billable', $targets);
        $this->assertContains('Refundable', $targets);
    }

    public function test_extracts_interface(): void
    {
        $code = <<<'PHP'
        <?php
        interface PaymentGateway
        {
            public function charge(int $amount): bool;
        }
        PHP;

        $result = $this->extractor->extract('PaymentGateway.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('interface', $result->nodes[0]->metadata['class_type']);
    }

    public function test_extracts_enum(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string
        {
            case Active = 'active';
            case Inactive = 'inactive';
        }
        PHP;

        $result = $this->extractor->extract('Status.php', $code);

        $this->assertCount(1, $result->nodes);
        $this->assertSame('enum', $result->nodes[0]->metadata['class_type']);
    }

    public function test_supports_php_files(): void
    {
        $this->assertTrue($this->extractor->supports('test.php', '<?php class Foo {}'));
        $this->assertFalse($this->extractor->supports('test.js', 'class Foo {}'));
        $this->assertFalse($this->extractor->supports('test.php', 'no php tag'));
    }

    public function test_handles_invalid_php(): void
    {
        $result = $this->extractor->extract('bad.php', '<?php this is not valid {{{');
        $this->assertTrue($result->isEmpty());
    }
}
