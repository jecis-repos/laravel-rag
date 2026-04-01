<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts Eloquent relationship definitions (hasMany, belongsTo, etc.)
 * and creates typed edges in the knowledge graph.
 */
final class RelationshipExtractor extends AbstractPhpExtractor
{
    private const RELATIONSHIP_METHODS = [
        'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
        'hasOneThrough', 'hasManyThrough',
        'morphOne', 'morphMany', 'morphTo', 'morphToMany', 'morphedByMany',
    ];

    public function type(): string
    {
        return 'relationship';
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        $methods = $this->findNodes($stmts, fn (Node $n) => $n instanceof Node\Stmt\ClassMethod);

        /** @var Node\Stmt\ClassMethod $method */
        foreach ($methods as $method) {
            $calls = $this->findNodes([$method], fn (Node $n) =>
                $n instanceof Node\Expr\MethodCall
                && $n->var instanceof Node\Expr\Variable
                && $n->var->name === 'this'
                && $n->name instanceof Node\Identifier
                && in_array($n->name->toString(), self::RELATIONSHIP_METHODS, true)
            );

            foreach ($calls as $call) {
                /** @var Node\Expr\MethodCall $call */
                $relationType = $call->name->toString();

                // First argument is the related model class
                $relatedModel = null;
                if (isset($call->args[0]) && $call->args[0] instanceof Node\Arg) {
                    $arg = $call->args[0]->value;
                    if ($arg instanceof Node\Expr\ClassConstFetch
                        && $arg->name instanceof Node\Identifier
                        && $arg->name->toString() === 'class') {
                        $relatedModel = $arg->class instanceof Node\Name
                            ? $arg->class->toString()
                            : null;
                    }
                }

                $nodes[] = new KnowledgeNode(
                    sourcePath: $filePath,
                    type: 'relationship',
                    name: $method->name->toString(),
                    content: $this->nodeSource($method, $content),
                    startLine: $method->getStartLine(),
                    endLine: $method->getEndLine(),
                    metadata: [
                        'relation_type' => $relationType,
                        'related_model' => $relatedModel,
                    ],
                );

                if ($relatedModel) {
                    $edges[] = new KnowledgeEdge(
                        sourcePath: $filePath,
                        targetPath: $relatedModel,
                        edgeType: $relationType,
                        metadata: ['method' => $method->name->toString()],
                    );
                }
            }
        }

        return new ExtractionResult($nodes, $edges);
    }
}
