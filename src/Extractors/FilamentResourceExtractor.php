<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts Filament resource definitions: the model binding, form schema,
 * table columns, and actions.
 */
final class FilamentResourceExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'filament_resource';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content) && str_contains($content, 'extends Resource');
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        // Find the $model property
        $properties = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Stmt\Property
            && count($n->props) > 0
            && $n->props[0]->name->toString() === 'model'
        );

        $model = null;
        foreach ($properties as $prop) {
            /** @var Node\Stmt\Property $prop */
            $default = $prop->props[0]->default ?? null;
            if ($default instanceof Node\Expr\ClassConstFetch
                && $default->class instanceof Node\Name) {
                $model = $default->class->toString();
            }
        }

        // Find the resource class name
        $classes = $this->findNodes($stmts, fn (Node $n) => $n instanceof Node\Stmt\Class_);
        $className = 'UnknownResource';
        foreach ($classes as $class) {
            /** @var Node\Stmt\Class_ $class */
            $className = $this->resolveClassName($class, $stmts);
            break;
        }

        $nodes[] = new KnowledgeNode(
            sourcePath: $filePath,
            type: 'filament_resource',
            name: $className,
            content: $content,
            metadata: [
                'model' => $model,
            ],
        );

        if ($model) {
            $edges[] = new KnowledgeEdge($filePath, $model, 'manages_model');
        }

        return new ExtractionResult($nodes, $edges);
    }
}
