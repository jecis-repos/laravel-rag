<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts middleware classes — identifies the handle() method and any
 * middleware groups/aliases defined in bootstrap or kernel.
 */
final class MiddlewareExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'middleware';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content)
            && (str_contains($content, 'function handle') && str_contains($content, '$next'));
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $classes = $this->findNodes($stmts, fn (Node $n) => $n instanceof Node\Stmt\Class_);

        $nodes = [];
        foreach ($classes as $class) {
            /** @var Node\Stmt\Class_ $class */
            $name = $this->resolveClassName($class, $stmts);

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'middleware',
                name: $name,
                content: $this->nodeSource($class, $content),
                startLine: $class->getStartLine(),
                endLine: $class->getEndLine(),
                metadata: ['class' => $name],
            );
        }

        return new ExtractionResult($nodes);
    }
}
