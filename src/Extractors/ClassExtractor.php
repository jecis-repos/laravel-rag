<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts class declarations, interfaces, traits, and enums.
 * Creates edges for extends/implements relationships.
 */
final class ClassExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'class';
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        $classes = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Stmt\Class_
            || $n instanceof Node\Stmt\Interface_
            || $n instanceof Node\Stmt\Trait_
            || $n instanceof Node\Stmt\Enum_
        );

        foreach ($classes as $class) {
            $name = match (true) {
                $class instanceof Node\Stmt\Class_ => $this->resolveClassName($class, $stmts),
                $class instanceof Node\Stmt\Interface_ => $class->name?->toString() ?? 'anonymous',
                $class instanceof Node\Stmt\Trait_ => $class->name?->toString() ?? 'anonymous',
                $class instanceof Node\Stmt\Enum_ => $class->name?->toString() ?? 'anonymous',
                default => 'unknown',
            };

            $classType = match (true) {
                $class instanceof Node\Stmt\Interface_ => 'interface',
                $class instanceof Node\Stmt\Trait_ => 'trait',
                $class instanceof Node\Stmt\Enum_ => 'enum',
                default => 'class',
            };

            $metadata = ['class_type' => $classType];

            if ($class instanceof Node\Stmt\Class_) {
                if ($class->extends) {
                    $parent = $class->extends->toString();
                    $metadata['extends'] = $parent;
                    $edges[] = new KnowledgeEdge($filePath, $parent, 'extends');
                }

                foreach ($class->implements as $interface) {
                    $iface = $interface->toString();
                    $metadata['implements'][] = $iface;
                    $edges[] = new KnowledgeEdge($filePath, $iface, 'implements');
                }
            }

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: $classType,
                name: $name,
                content: $this->nodeSource($class, $content),
                startLine: $class->getStartLine(),
                endLine: $class->getEndLine(),
                metadata: $metadata,
            );
        }

        return new ExtractionResult($nodes, $edges);
    }
}
