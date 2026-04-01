<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts service container bindings: $this->app->bind(), singleton(), scoped().
 * Creates edges from abstract to concrete.
 */
final class ServiceBindingExtractor extends AbstractPhpExtractor
{
    private const BIND_METHODS = ['bind', 'singleton', 'scoped', 'instance'];

    public function type(): string
    {
        return 'service_binding';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content)
            && (str_contains($content, '->bind(')
                || str_contains($content, '->singleton(')
                || str_contains($content, '->scoped('));
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        $calls = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Expr\MethodCall
            && $n->name instanceof Node\Identifier
            && in_array($n->name->toString(), self::BIND_METHODS, true)
        );

        foreach ($calls as $call) {
            /** @var Node\Expr\MethodCall $call */
            $bindType = $call->name->toString();
            $abstract = $this->resolveArg($call->args[0] ?? null);
            $concrete = $this->resolveArg($call->args[1] ?? null);

            if (!$abstract) {
                continue;
            }

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'service_binding',
                name: $abstract,
                content: $this->nodeSource($call, $content),
                startLine: $call->getStartLine(),
                endLine: $call->getEndLine(),
                metadata: [
                    'bind_type' => $bindType,
                    'abstract' => $abstract,
                    'concrete' => $concrete,
                ],
            );

            if ($concrete && $concrete !== $abstract) {
                $edges[] = new KnowledgeEdge($abstract, $concrete, 'binds_to', ['bind_type' => $bindType]);
            }
        }

        return new ExtractionResult($nodes, $edges);
    }

    private function resolveArg(?Node\Arg $arg): ?string
    {
        if (!$arg) {
            return null;
        }

        $value = $arg->value;

        if ($value instanceof Node\Expr\ClassConstFetch
            && $value->class instanceof Node\Name
            && $value->name instanceof Node\Identifier
            && $value->name->toString() === 'class') {
            return $value->class->toString();
        }

        if ($value instanceof Node\Scalar\String_) {
            return $value->value;
        }

        return null;
    }
}
