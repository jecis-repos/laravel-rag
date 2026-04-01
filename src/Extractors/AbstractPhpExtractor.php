<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Contracts\ExtractorContract;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Base class for PHP AST extractors using nikic/php-parser.
 */
abstract class AbstractPhpExtractor implements ExtractorContract
{
    public function supports(string $filePath, string $content): bool
    {
        return str_ends_with($filePath, '.php') && str_contains($content, '<?php');
    }

    /**
     * Parse PHP source and return the AST.
     *
     * @return Node\Stmt[]|null
     */
    protected function parse(string $content): ?array
    {
        $parser = (new ParserFactory())->createForHostVersion();

        try {
            return $parser->parse($content);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find all nodes matching a predicate via traversal.
     *
     * @return Node[]
     */
    protected function findNodes(array $stmts, callable $predicate): array
    {
        $found = [];
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($predicate, $found) extends NodeVisitorAbstract {
            public function __construct(
                private readonly \Closure $predicate,
                private array &$found,
            ) {}

            public function enterNode(Node $node): ?int
            {
                if (($this->predicate)($node)) {
                    $this->found[] = $node;
                }
                return null;
            }
        });
        $traverser->traverse($stmts);

        return $found;
    }

    /**
     * Resolve the fully-qualified class name from a Class_ node.
     */
    protected function resolveClassName(Node\Stmt\Class_ $class, array $stmts): string
    {
        $namespace = '';
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $namespace = $stmt->name?->toString() ?? '';
                break;
            }
        }

        $name = $class->name?->toString() ?? 'anonymous';

        return $namespace ? $namespace . '\\' . $name : $name;
    }

    /**
     * Get the source text for a node, given the full file content.
     */
    protected function nodeSource(Node $node, string $content): string
    {
        $lines = explode("\n", $content);
        $start = $node->getStartLine() - 1;
        $end = $node->getEndLine();

        return implode("\n", array_slice($lines, $start, $end - $start));
    }
}
